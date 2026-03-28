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
    $rateRow = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY effective_date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
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

    // มิเตอร์ล่าสุดก่อนเดือนเป้าหมาย (ค่าปลาย = ค่าต้นของเดือนเป้าหมาย)
    $prevStmt = $pdo->prepare(
        "SELECT utl_water_end, utl_elec_end
         FROM utility
         WHERE ctr_id = ? AND utl_date < ?
         ORDER BY utl_date DESC, utl_id DESC
         LIMIT 1"
    );
    $prevStmt->execute([$ctrId, $targetMonthStart]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

    $prevWater = $prev ? (int)$prev['utl_water_end'] : 0;
    $prevElec  = $prev ? (int)$prev['utl_elec_end']  : 0;
    
    // ตรวจสอบว่าเป็นการจดมิเตอร์ครั้งแรกหรือไม่ (ไม่มีการบันทึกใด ๆ มาก่อน)
    $isFirstReading = !$prev;  // $prev will be null if no previous record exists

    // ตรวจสอบว่าบันทึกเดือนนี้แล้วหรือยัง
    $currStmt = $pdo->prepare(
        "SELECT utl_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
         FROM utility
         WHERE ctr_id = ? AND utl_date >= ? AND utl_date < ?
         ORDER BY utl_date DESC, utl_id DESC
         LIMIT 1"
    );
    $currStmt->execute([$ctrId, $targetMonthStart, $targetMonthEnd]);
    $curr = $currStmt->fetch(PDO::FETCH_ASSOC);

    if ($curr) {
        $prevWater = (int)$curr['utl_water_start'];
        $prevElec = (int)$curr['utl_elec_start'];
    }

    echo json_encode([
        'prev_water'  => $prevWater,
        'prev_elec'   => $prevElec,
        'saved'       => $curr ? true : false,
        'curr_water'  => $curr ? (int)$curr['utl_water_end'] : null,
        'curr_elec'   => $curr ? (int)$curr['utl_elec_end']  : null,
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
