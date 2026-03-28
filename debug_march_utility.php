<?php
require 'ConnectDB.php';
$pdo = connectDB();

// Room 12 contract
$roomStmt = $pdo->prepare("SELECT room_id FROM room WHERE room_number = 12");
$roomStmt->execute();
$room = $roomStmt->fetch(PDO::FETCH_ASSOC);
$roomId = $room ? (int)$room['room_id'] : 0;

$ctrStmt = $pdo->prepare("SELECT ctr_id FROM contract WHERE room_id = ? AND ctr_status = '0' ORDER BY ctr_id DESC LIMIT 1");
$ctrStmt->execute([$roomId]);
$ctr = $ctrStmt->fetch(PDO::FETCH_ASSOC);
$ctrId = $ctr ? (int)$ctr['ctr_id'] : 0;

echo "Room 12: room_id=$roomId, ctr_id=$ctrId\n\n";

// Check March 2026 data
$marchStmt = $pdo->prepare("
    SELECT utl_id, utl_date, MONTH(utl_date) AS m, YEAR(utl_date) AS y,
           utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
    FROM utility 
    WHERE ctr_id = ? AND DATE_FORMAT(utl_date, '%Y-%m') = '2026-03'
");
$marchStmt->execute([$ctrId]);
$march = $marchStmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== March 2026 Data ===\n";
echo "Count: " . count($march) . "\n";
foreach ($march as $row) {
    echo "ID: {$row['utl_id']}, Date: {$row['utl_date']}, M/Y: {$row['m']}/{$row['y']}\n";
    echo "  Water: {$row['utl_water_start']} → {$row['utl_water_end']}\n";
    echo "  Elec:  {$row['utl_elec_start']} → {$row['utl_elec_end']}\n\n";
}

// Check with MONTH/YEAR query
echo "=== Query with MONTH/YEAR ===\n";
$queryStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt
    FROM utility 
    WHERE ctr_id = ? AND MONTH(utl_date) = 3 AND YEAR(utl_date) = 2026
");
$queryStmt->execute([$ctrId]);
$queryResult = $queryStmt->fetch(PDO::FETCH_ASSOC);
echo "Count: " . ($queryResult['cnt'] ?? 0) . "\n";
?>
