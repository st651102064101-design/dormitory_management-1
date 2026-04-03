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

// CSRF validation
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token ไม่ถูกต้อง']);
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

    // Fallback: ถ้าไม่มี utility เดือนก่อนของสัญญานี้ → ดึงค่ามิเตอร์ล่าสุดของห้องเดียวกันจากทุกสัญญา
    // รองรับกรณีผู้เช่าเก่าคืนห้อง แล้วผู้เช่าใหม่เข้า — ค่ามิเตอร์ต้องต่อเนื่อง
    if (!$prev) {
        $roomStmtPrev = $pdo->prepare("SELECT room_id FROM contract WHERE ctr_id = ? LIMIT 1");
        $roomStmtPrev->execute([$ctrId]);
        $roomForPrev = $roomStmtPrev->fetch(PDO::FETCH_ASSOC);
        $roomIdForPrev = $roomForPrev ? (int)$roomForPrev['room_id'] : 0;
        if ($roomIdForPrev > 0) {
            $roomPrevStmt = $pdo->prepare(
                "SELECT u.utl_water_end, u.utl_elec_end
                 FROM utility u
                 INNER JOIN contract c ON u.ctr_id = c.ctr_id
                 WHERE c.room_id = ? AND u.utl_date < ?
                 ORDER BY u.utl_date DESC, u.utl_id DESC
                 LIMIT 1"
            );
            $roomPrevStmt->execute([$roomIdForPrev, $targetMonthStart]);
            $roomPrev = $roomPrevStmt->fetch(PDO::FETCH_ASSOC);
            if ($roomPrev) {
                $prev = $roomPrev;
                $prevWater = (int)$roomPrev['utl_water_end'];
                $prevElec  = (int)$roomPrev['utl_elec_end'];
            }
        }
    }

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

    // ถ้าไม่มี expense record อยู่แล้ว ให้ INSERT ใหม่ (ไม่ใช่ครั้งแรก = มีค่าใช้จ่าย)
    // ตรวจสอบด้วย SELECT จริงๆ แทน rowCount() เพราะ MySQL คืน 0 แม้ record มีอยู่แต่ค่าไม่เปลี่ยน
    $chkExpStmt = $pdo->prepare("SELECT exp_id FROM expense WHERE ctr_id = ? AND MONTH(exp_month) = ? AND YEAR(exp_month) = ? LIMIT 1");
    $chkExpStmt->execute([$ctrId, $meterMonth, $meterYear]);
    $expExists = $chkExpStmt->fetch();
    if (!$expExists && !$isFirstReading) {
        $roomStmt = $pdo->prepare("
            SELECT rt.type_price
            FROM contract c
            LEFT JOIN room r ON c.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            WHERE c.ctr_id = ?
        ");
        $roomStmt->execute([$ctrId]);
        $roomRow = $roomStmt->fetch(PDO::FETCH_ASSOC);
        $roomPrice = (int)($roomRow['type_price'] ?? 0);
        $expTotal  = $roomPrice + $elecCost + $waterCost;
        $expMonth  = sprintf('%04d-%02d-01', $meterYear, $meterMonth);
        $insertExpStmt = $pdo->prepare("
            INSERT INTO expense
                (exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water,
                 room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '2', ?)
            ON DUPLICATE KEY UPDATE
                exp_elec_unit = VALUES(exp_elec_unit),
                exp_water_unit = VALUES(exp_water_unit),
                rate_elec = VALUES(rate_elec),
                rate_water = VALUES(rate_water),
                exp_elec_chg = VALUES(exp_elec_chg),
                exp_water = VALUES(exp_water),
                exp_total = VALUES(exp_total)
        ");
        $insertExpStmt->execute([
            $expMonth, $elecUsed, $waterUsed, $rateElec, $rateWater,
            $roomPrice, $elecCost, $waterCost, $expTotal, $ctrId,
        ]);
    }

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
