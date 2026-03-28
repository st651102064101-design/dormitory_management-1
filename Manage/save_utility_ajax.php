<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
require_once __DIR__ . '/../includes/water_calc.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$ctrId     = isset($_POST['ctr_id'])     ? (int)$_POST['ctr_id']     : 0;
$waterNew  = isset($_POST['water_new'])  && $_POST['water_new'] !== '' ? (int)$_POST['water_new']  : null;
$elecNew   = isset($_POST['elec_new'])   && $_POST['elec_new']  !== '' ? (int)$_POST['elec_new']   : null;
$meterMonth = isset($_POST['meter_month']) ? (int)$_POST['meter_month'] : (int)date('n');
$meterYear  = isset($_POST['meter_year'])  ? (int)$_POST['meter_year']  : (int)date('Y');

if ($ctrId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ctr_id']);
    exit;
}
if ($waterNew === null && $elecNew === null) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกตัวเลขมิเตอร์อย่างน้อย 1 ค่า']);
    exit;
}

try {
    $pdo = connectDB();
    
    $targetMonthStart = sprintf('%04d-%02d-01', $meterYear, $meterMonth);
    $targetMonthEnd = (new DateTimeImmutable($targetMonthStart))->modify('+1 month')->format('Y-m-d');

    // อัตราค่าน้ำ-ไฟล่าสุด
    try {
        $rateRow = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY effective_date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rateRow = null;
    }
    $rateWater = $rateRow ? (float)$rateRow['rate_water'] : 18.0;
    $rateElec  = $rateRow ? (float)$rateRow['rate_elec']  : 8.0;

    // ค่าปลายล่าสุดก่อนเดือนเป้าหมาย → เป็นค่าต้นของเดือนนี้
    // คำนวณเดือน/ปีที่แล้ว
    $prevMonth = $meterMonth > 1 ? $meterMonth - 1 : 12;
    $prevYear = $meterMonth > 1 ? $meterYear : $meterYear - 1;
    
    $prevStmt = $pdo->prepare(
        "SELECT utl_water_end, utl_elec_end
         FROM utility
         WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?
         ORDER BY utl_date DESC, utl_id DESC
         LIMIT 1"
    );
    $prevStmt->execute([$ctrId, $prevMonth, $prevYear]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
    $prevWater = $prev ? (int)$prev['utl_water_end'] : 0;
    $prevElec  = $prev ? (int)$prev['utl_elec_end']  : 0;

    // ตรวจซ้ำ - ถ้ามีแล้วจะเป็นการแก้ไข (UPDATE) ไม่ใช่ข้อผิดพลาด
    $checkStmt = $pdo->prepare(
        "SELECT utl_id
         FROM utility
         WHERE ctr_id = ? AND utl_date >= ? AND utl_date < ?
         ORDER BY utl_date DESC, utl_id DESC
         LIMIT 1"
    );
    $checkStmt->execute([$ctrId, $targetMonthStart, $targetMonthEnd]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $existingUtlId = $existingRecord ? (int)$existingRecord['utl_id'] : null;

    // Validate: new >= prev
    if ($waterNew !== null && $waterNew < $prevWater) {
        echo json_encode(['success' => false, 'error' => "ค่ามิเตอร์น้ำใหม่ ({$waterNew}) ต้องไม่น้อยกว่าค่าเดิม ({$prevWater})"]);
        exit;
    }
    if ($elecNew !== null && $elecNew < $prevElec) {
        echo json_encode(['success' => false, 'error' => "ค่ามิเตอร์ไฟใหม่ ({$elecNew}) ต้องไม่น้อยกว่าค่าเดิม ({$prevElec})"]);
        exit;
    }

    $waterStart = $prevWater;
    $elecStart  = $prevElec;
    $waterEnd   = $waterNew  ?? $prevWater;
    $elecEnd    = $elecNew   ?? $prevElec;
    
    // ใช้วันสุดท้ายของเดือนที่บันทึก (หรือวันนี้ถ้าเป็นเดือนปัจจุบัน)
    $meterDateDt = new DateTimeImmutable(sprintf('%04d-%02d-01', $meterYear, $meterMonth));
    $lastDayOfMonth = (int)$meterDateDt->format('t');
    $currentDay = (int)date('d');
    $meterDay = min($currentDay, $lastDayOfMonth);
    $meterDate = $meterYear . '-' . str_pad((string)$meterMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$meterDay, 2, '0', STR_PAD_LEFT);

    // บันทึก utility - INSERT หรือ UPDATE ถ้ามีอยู่แล้ว
    if ($existingUtlId) {
        // UPDATE existing record
        $updateUtlStmt = $pdo->prepare(
            "UPDATE utility
             SET utl_water_start = ?, utl_water_end = ?, utl_elec_start = ?, utl_elec_end = ?, utl_date = ?
             WHERE utl_id = ?"
        );
        $updateUtlStmt->execute([$waterStart, $waterEnd, $elecStart, $elecEnd, $meterDate, $existingUtlId]);
    } else {
        // INSERT new record
        $insertStmt = $pdo->prepare(
            "INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insertStmt->execute([$ctrId, $waterStart, $waterEnd, $elecStart, $elecEnd, $meterDate]);
    }

    // คำนวณค่าใช้จ่าย - แต่ถ้าเป็นการจดมิเตอร์ครั้งแรก ให้บันทึกเฉพาะค่าเบื้องต้น ไม่คิดค่าใช้จ่าย
    $waterUsed = $waterEnd - $waterStart;
    $elecUsed  = $elecEnd  - $elecStart;
    
    // ตรวจสอบว่าเป็นการจดมิเตอร์ครั้งแรกหรือไม่ (ไม่มีค่า utl_water_start ที่มา จาก previous record)
    // ถ้า $prev เป็น empty/null แสดงว่าไม่มี utility record ก่อนหน้านี้ = ครั้งแรก
    $isFirstReading = ($prev === false || $prev === null);
    
    if ($isFirstReading) {
        // ครั้งแรกของการจดมิเตอร์ - ไม่คิดค่าใช้จ่าย เฉพาะบันทึกค่าเบื้องต้น
        $waterCost = 0;
        $elecCost  = 0;
    } else {
        // มีการจดมิเตอร์ก่อนหน้า - คิดค่าใช้จ่ายปกติ
        $waterCost = calculateWaterCost($waterUsed);
        $elecCost  = (int)round($elecUsed * $rateElec);
    }

    // อัปเดต expense เดือนนี้
    $updateExpStmt = $pdo->prepare("
        UPDATE expense SET
            exp_elec_unit = ?, exp_water_unit = ?,
            rate_elec = ?, rate_water = ?,
            exp_elec_chg = ?, exp_water = ?,
            exp_total = room_price + ? + ?
        WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ?
    ");
    $updateExpStmt->execute([
        $elecUsed, $waterUsed,
        $rateElec, $rateWater,
        $elecCost, $waterCost,
        $elecCost, $waterCost,
        $ctrId, $meterMonth, $meterYear,
    ]);

    echo json_encode([
        'success'     => true,
        'message'     => 'บันทึกมิเตอร์สำเร็จ',
        'water_used'  => $waterUsed,
        'elec_used'   => $elecUsed,
        'water_cost'  => $waterCost,
        'elec_cost'   => $elecCost,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
