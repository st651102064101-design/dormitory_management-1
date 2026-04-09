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

echo "=== ROOM 12 DEBUG ===\n";
echo "Room ID: $roomId, CTR ID: $ctrId\n\n";

// Get ALL utility records
$allStmt = $pdo->prepare("SELECT utl_id, ctr_id, utl_date, MONTH(utl_date) AS m, YEAR(utl_date) AS y, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility WHERE ctr_id = ? ORDER BY utl_date DESC");
$allStmt->execute([$ctrId]);
$all = $allStmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== ALL Utility Records ===\n";
echo "Total: " . count($all) . "\n";
foreach ($all as $row) {
    $ym = $row['y'] . '-' . str_pad((string)$row['m'], 2, '0', STR_PAD_LEFT);
    echo "ID: {$row['utl_id']}, Date: {$row['utl_date']}, YM: $ym\n";
    echo "  Water: {$row['utl_water_start']} → {$row['utl_water_end']} (hasRealData: " . (($row['utl_water_end'] !== $row['utl_water_start'] || $row['utl_elec_end'] !== $row['utl_elec_start']) ? 'YES' : 'NO') . ")\n";
    echo "  Elec:  {$row['utl_elec_start']} → {$row['utl_elec_end']}\n\n";
}

// Check February (prev of March)
echo "=== February 2026 ===\n";
$febStmt = $pdo->prepare("
    SELECT COUNT(*) as cnt FROM utility 
    WHERE ctr_id = ? AND MONTH(utl_date) = 2 AND YEAR(utl_date) = 2026
");
$febStmt->execute([$ctrId]);
$feb = $febStmt->fetch(PDO::FETCH_ASSOC);
echo "Count: " . ($feb['cnt'] ?? 0) . "\n\n";

// Check March 2026
echo "=== March 2026 Logic ===\n";
$marchCheckStmt = $pdo->prepare("
    SELECT utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility 
    WHERE ctr_id = ? AND MONTH(utl_date) = 3 AND YEAR(utl_date) = 2026
");
$marchCheckStmt->execute([$ctrId]);
$marchData = $marchCheckStmt->fetch(PDO::FETCH_ASSOC);

if ($marchData) {
    echo "Found March data!\n";
    echo "  Water: {$marchData['utl_water_start']} → {$marchData['utl_water_end']}\n";
    echo "  Elec:  {$marchData['utl_elec_start']} → {$marchData['utl_elec_end']}\n";
    
    $hasRealData = (
        (int)$marchData['utl_water_end'] !== (int)$marchData['utl_water_start'] ||
        (int)$marchData['utl_elec_end'] !== (int)$marchData['utl_elec_start']
    );
    echo "  hasRealData: " . ($hasRealData ? 'YES' : 'NO') . "\n";
    echo "  water_new value: " . ($hasRealData ? (int)$marchData['utl_water_end'] : 'empty') . "\n";
} else {
    echo "No March data found!\n";
}
?>
