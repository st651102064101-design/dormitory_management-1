<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_username'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../ConnectDB.php';
header('Content-Type: application/json; charset=utf-8');

$ctrId = isset($_GET['ctr_id']) ? (int)$_GET['ctr_id'] : 0;
$targetMonth = isset($_GET['target_month']) ? (int)$_GET['target_month'] : 0;
$targetYear = isset($_GET['target_year']) ? (int)$_GET['target_year'] : 0;

if ($ctrId <= 0) {
    echo json_encode(['error' => 'Invalid ctr_id']);
    exit;
}

try {
    $pdo = connectDB();

    $now = new DateTimeImmutable();
    // Use provided target month/year if given, otherwise use current month
    $meterMonth = $targetMonth > 0 && $targetMonth <= 12 ? $targetMonth : (int)$now->format('n');
    $meterYear = $targetYear > 0 ? $targetYear : (int)$now->format('Y');
    $targetMonthStart = sprintf('%04d-%02d-01', $meterYear, $meterMonth);
    $targetMonthEnd = (new DateTimeImmutable($targetMonthStart))->modify('+1 month')->format('Y-m-d');

    // อัตราค่าน้ำ-ไฟล่าสุด และค่าน้ำเหมาจ่าย
    $rateRow = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY effective_date DESC, rate_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $rateWater = $rateRow ? (float)$rateRow['rate_water'] : 18.0;
    $rateElec  = $rateRow ? (float)$rateRow['rate_elec']  : 8.0;
    
    // ค่าน้ำเหมาจ่าย (tiered pricing)
    $settingsStmt1 = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $settingsStmt1->execute(['water_base_units']);
    $waterBaseUnits = (int)($settingsStmt1->fetchColumn() ?: 10);
    
    $settingsStmt2 = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $settingsStmt2->execute(['water_base_price']);
    $waterBasePrice = (int)($settingsStmt2->fetchColumn() ?: 200);
    
    $settingsStmt3 = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $settingsStmt3->execute(['water_excess_rate']);
    $waterExcessRate = (int)($settingsStmt3->fetchColumn() ?: 25);

    // คำนวณเดือน/ปีที่แล้ว
    $prevMonth = $meterMonth > 1 ? $meterMonth - 1 : 12;
    $prevYear = $meterMonth > 1 ? $meterYear : $meterYear - 1;

    // มิเตอร์ล่าสุดก่อนเดือนเป้าหมาย (ค่าปลาย = ค่าต้นของเดือนเป้าหมาย)
    // ดึงจากเดือนก่อนหน้า ไม่ว่า utl_date จะเป็นวันไหนก็ตาม
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

    // ดึง room_id ของสัญญานี้
    $ctrRow = $pdo->prepare("SELECT ctr_start, room_id FROM contract WHERE ctr_id = ? LIMIT 1");
    $ctrRow->execute([$ctrId]);
    $ctrData = $ctrRow->fetch(PDO::FETCH_ASSOC);
    $ctrStartYm = $ctrData ? date('Y-m', strtotime((string)$ctrData['ctr_start'])) : null;
    $ctrRoomId = $ctrData ? (int)$ctrData['room_id'] : 0;

    // Fallback: ถ้าไม่มี utility เดือนก่อนของสัญญานี้ → ดึงค่ามิเตอร์ล่าสุดของห้องเดียวกันจากทุกสัญญา
    // รองรับกรณีผู้เช่าเก่าคืนห้อง แล้วผู้เช่าใหม่เข้า — ค่ามิเตอร์ต้องต่อเนื่อง
    if (!$prev && $ctrRoomId > 0) {
        $roomPrevStmt = $pdo->prepare(
            "SELECT u.utl_water_end, u.utl_elec_end
             FROM utility u
             INNER JOIN contract c ON u.ctr_id = c.ctr_id
             WHERE c.room_id = ? AND u.utl_date < ?
             ORDER BY u.utl_date DESC, u.utl_id DESC
             LIMIT 1"
        );
        $roomPrevStmt->execute([$ctrRoomId, $targetMonthStart]);
        $roomPrev = $roomPrevStmt->fetch(PDO::FETCH_ASSOC);
        if ($roomPrev) {
            $prev = $roomPrev; // ใช้ค่าห้องเดิมจากสัญญาก่อนหน้า
            $prevWater = (int)$roomPrev['utl_water_end'];
            $prevElec  = (int)$roomPrev['utl_elec_end'];
        }
    }

    // การจดมิเตอร์ครั้งแรก = เดือนเป้าหมาย ตรงกับเดือนเริ่มสัญญา (ctr_start) เท่านั้น
    // ไม่ใช่ "ไม่มี utility เดือนก่อน" เพราะเดือนแรกสุดไม่มี utility อยู่แล้ว
    $isFirstReading = $ctrStartYm !== null
        && date('Y-m', strtotime($targetMonthStart)) === $ctrStartYm;

    // ตรวจสอบว่าบันทึกเดือนนี้แล้วหรือยัง (รองรับ partial save: น้ำจดแล้ว/ไฟยังไม่จด หรือกลับกัน)
    $currStmt = $pdo->prepare(
        "SELECT utl_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
         FROM utility
         WHERE ctr_id = ? AND utl_date >= ? AND utl_date < ?
         ORDER BY utl_date DESC, utl_id DESC
         LIMIT 1"
    );
    $currStmt->execute([$ctrId, $targetMonthStart, $targetMonthEnd]);
    $curr = $currStmt->fetch(PDO::FETCH_ASSOC);

    // ตรวจสอบแยกแต่ละมิเตอร์
    $waterSaved = $curr && (int)($curr['utl_water_end'] ?? 0) > 0;
    $elecSaved  = $curr && (int)($curr['utl_elec_end']  ?? 0) > 0;
    $allSaved   = $waterSaved && $elecSaved;

    // ถ้าเป็น all-zero first record → ถือว่ายังไม่ได้จดจริง
    $isAllZeroFirst = $isFirstReading && $curr
        && (int)($curr['utl_water_end'] ?? 0) === 0
        && (int)($curr['utl_elec_end']  ?? 0) === 0;
    if ($isAllZeroFirst) {
        $waterSaved = false;
        $elecSaved  = false;
        $allSaved   = false;
    }

    if ($curr && ($waterSaved || $elecSaved)) {
        if ($waterSaved) $prevWater = (int)$curr['utl_water_start'];
        if ($elecSaved)  $prevElec  = (int)$curr['utl_elec_start'];
    } elseif (!$prev) {
        // ไม่มีทั้ง utility เดือนนี้และเดือนก่อน — ใช้ค่า checkin_record เป็นค่าเริ่มต้น
        $chkStmt = $pdo->prepare("SELECT water_meter_start, elec_meter_start FROM checkin_record WHERE ctr_id = ? LIMIT 1");
        $chkStmt->execute([$ctrId]);
        $chkRow = $chkStmt->fetch(PDO::FETCH_ASSOC);
        if ($chkRow && (int)$chkRow['water_meter_start'] > 0 && (int)$chkRow['elec_meter_start'] > 0) {
            $prevWater = (int)$chkRow['water_meter_start'];
            $prevElec  = (int)$chkRow['elec_meter_start'];
        }
    }

    echo json_encode([
        'prev_water'  => $prevWater,
        'prev_elec'   => $prevElec,
        'saved'       => $allSaved,
        'water_saved' => $waterSaved,
        'elec_saved'  => $elecSaved,
        'curr_water'  => $waterSaved ? (int)$curr['utl_water_end'] : null,
        'curr_elec'   => $elecSaved  ? (int)$curr['utl_elec_end']  : null,
        'is_first_reading' => $isFirstReading,
        'meter_month' => $meterMonth,
        'meter_year'  => $meterYear,
        'rate_water'  => $rateWater,
        'rate_elec'   => $rateElec,
        'water_base_units'  => $waterBaseUnits,
        'water_base_price'  => $waterBasePrice,
        'water_excess_rate' => $waterExcessRate,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
