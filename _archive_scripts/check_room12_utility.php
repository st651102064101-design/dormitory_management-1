<?php
require 'ConnectDB.php';
$pdo = connectDB();

// ห้อง 12
$roomStmt = $pdo->prepare("SELECT room_id FROM room WHERE room_number = 12");
$roomStmt->execute();
$room = $roomStmt->fetch(PDO::FETCH_ASSOC);
$roomId = $room ? (int)$room['room_id'] : 0;

echo "<!-- ห้อง 12 -->\n";
echo "Room ID: $roomId\n\n";

// ดึง contract ที่ active
$ctrStmt = $pdo->prepare("SELECT ctr_id FROM contract WHERE room_id = ? AND ctr_status = '0' ORDER BY ctr_id DESC LIMIT 1");
$ctrStmt->execute([$roomId]);
$ctr = $ctrStmt->fetch(PDO::FETCH_ASSOC);
$ctrId = $ctr ? (int)$ctr['ctr_id'] : 0;

echo "Active Contract ID: $ctrId\n\n";

// ดึง utility record ทั้งหมดของห้อง 12
$utilStmt = $pdo->prepare("SELECT utl_id, ctr_id, utl_date, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility WHERE ctr_id = ? ORDER BY utl_date ASC");
$utilStmt->execute([$ctrId]);
$utils = $utilStmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Utility Records for Room 12 ===\n";
echo "Total Records: " . count($utils) . "\n\n";

foreach ($utils as $u) {
    $ym = date('Y-m', strtotime($u['utl_date']));
    echo "Record ID: {$u['utl_id']}\n";
    echo "  Month: $ym\n";
    echo "  Date: {$u['utl_date']}\n";
    echo "  Water: {$u['utl_water_start']} → {$u['utl_water_end']}\n";
    echo "  Elec:  {$u['utl_elec_start']} → {$u['utl_elec_end']}\n";
    echo "\n";
}

// ตรวจสอบเฉพาะ March & April 2026
echo "\n=== Check March & April 2026 ===\n";

$marchStmt = $pdo->prepare("
    SELECT utl_water_end, utl_elec_end 
    FROM utility 
    WHERE ctr_id = ? AND MONTH(utl_date) = 3 AND YEAR(utl_date) = 2026
");
$marchStmt->execute([$ctrId]);
$march = $marchStmt->fetch(PDO::FETCH_ASSOC);

echo "March 2026: ";
if ($march) {
    echo "Water End: {$march['utl_water_end']}, Elec End: {$march['utl_elec_end']}\n";
} else {
    echo "NO DATA\n";
}

$aprilStmt = $pdo->prepare("
    SELECT utl_water_end, utl_elec_end 
    FROM utility 
    WHERE ctr_id = ? AND MONTH(utl_date) = 4 AND YEAR(utl_date) = 2026
");
$aprilStmt->execute([$ctrId]);
$april = $aprilStmt->fetch(PDO::FETCH_ASSOC);

echo "April 2026: ";
if ($april) {
    echo "Water End: {$april['utl_water_end']}, Elec End: {$april['utl_elec_end']}\n";
} else {
    echo "NO DATA\n";
}
?>
