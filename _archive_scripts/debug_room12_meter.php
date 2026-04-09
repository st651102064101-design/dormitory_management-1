<?php
require 'ConnectDB.php';
$pdo = connectDB();

// Room 12
$roomStmt = $pdo->prepare("SELECT room_id FROM room WHERE room_number = 12");
$roomStmt->execute();
$room = $roomStmt->fetch(PDO::FETCH_ASSOC);
$roomId = $room ? (int)$room['room_id'] : 0;

// Contract
$ctrStmt = $pdo->prepare("SELECT ctr_id FROM contract WHERE room_id = ? AND ctr_status = '0' ORDER BY ctr_id DESC LIMIT 1");
$ctrStmt->execute([$roomId]);
$ctr = $ctrStmt->fetch(PDO::FETCH_ASSOC);
$ctrId = $ctr ? (int)$ctr['ctr_id'] : 0;

echo "Room 12: room_id=$roomId, ctr_id=$ctrId\n\n";

// Utility records
$utilStmt = $pdo->prepare("
    SELECT utl_id, ctr_id, utl_date, 
           DATE_FORMAT(utl_date, '%Y-%m') AS ym,
           utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
    FROM utility 
    WHERE ctr_id = ? 
    ORDER BY utl_date DESC
");
$utilStmt->execute([$ctrId]);
$utils = $utilStmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Utility Records ===\n";
echo "Count: " . count($utils) . "\n\n";
foreach ($utils as $u) {
    echo "ID: {$u['utl_id']}, ctr_id: {$u['ctr_id']}, Date: {$u['utl_date']}, YM: {$u['ym']}\n";
    echo "  Water: {$u['utl_water_start']} → {$u['utl_water_end']}\n";
    echo "  Elec:  {$u['utl_elec_start']} → {$u['utl_elec_end']}\n\n";
}

// Check if 2026-04 exists
$aprilCheck = $pdo->prepare("
    SELECT COUNT(*) as cnt FROM utility 
    WHERE ctr_id = ? AND DATE_FORMAT(utl_date, '%Y-%m') = '2026-04'
");
$aprilCheck->execute([$ctrId]);
$aprilResult = $aprilCheck->fetch(PDO::FETCH_ASSOC);

echo "=== April 2026 Check ===\n";
echo "Count: " . ($aprilResult['cnt'] ?? 0) . "\n";
?>
