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

// Check if March 2026 exists
$checkStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt FROM utility 
    WHERE ctr_id = ? AND MONTH(utl_date) = 3 AND YEAR(utl_date) = 2026
");
$checkStmt->execute([$ctrId]);
$check = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (($check['cnt'] ?? 0) > 0) {
    echo "✓ March 2026 utility record already exists\n";
    exit;
}

// Create March 2026 record (first reading)
echo "Creating March 2026 utility record...\n";

$insertStmt = $pdo->prepare("
    INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date)
    VALUES (?, 0, 1044, 0, 1044, '2026-03-29')
");

try {
    $insertStmt->execute([$ctrId]);
    echo "✓ Created March 2026 utility record\n";
    echo "  - Water: 0 → 1044\n";
    echo "  - Elec:  0 → 1044\n";
    echo "\nNow visit manage_utility.php and select March to verify\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
