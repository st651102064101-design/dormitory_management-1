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
if ($ctrId <= 0) {
    echo json_encode(['error' => 'Invalid ctr_id']);
    exit;
}

try {
    $pdo = connectDB();

    $now = new DateTimeImmutable();
    $meterMonth = (int)$now->format('n');
    $meterYear  = (int)$now->format('Y');

    // อัตราค่าน้ำ-ไฟล่าสุด
    $rateRow = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY effective_date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $rateWater = $rateRow ? (float)$rateRow['rate_water'] : 18.0;
    $rateElec  = $rateRow ? (float)$rateRow['rate_elec']  : 8.0;

    // มิเตอร์เดือนที่แล้ว (ค่าปลาย = ค่าต้นเดือนนี้)
    $prevStmt = $pdo->prepare(
        "SELECT utl_water_end, utl_elec_end FROM utility WHERE ctr_id = ? ORDER BY utl_date DESC LIMIT 1"
    );
    $prevStmt->execute([$ctrId]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

    $prevWater = $prev ? (int)$prev['utl_water_end'] : 0;
    $prevElec  = $prev ? (int)$prev['utl_elec_end']  : 0;

    // ตรวจสอบว่าบันทึกเดือนนี้แล้วหรือยัง
    $currStmt = $pdo->prepare(
        "SELECT utl_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
         FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?"
    );
    $currStmt->execute([$ctrId, $meterMonth, $meterYear]);
    $curr = $currStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'prev_water'  => $prevWater,
        'prev_elec'   => $prevElec,
        'saved'       => $curr ? true : false,
        'curr_water'  => $curr ? (int)$curr['utl_water_end'] : null,
        'curr_elec'   => $curr ? (int)$curr['utl_elec_end']  : null,
        'meter_month' => $meterMonth,
        'meter_year'  => $meterYear,
        'rate_water'  => $rateWater,
        'rate_elec'   => $rateElec,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
