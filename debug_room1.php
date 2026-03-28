<?php
require 'ConnectDB.php';
$pdo = connectDB();

// GetRoom 1 contract
$stmt = $pdo->prepare('SELECT ctr_id FROM contract WHERE room_id = 1 AND ctr_status = "0" ORDER BY ctr_id DESC LIMIT 1');
$stmt->execute();
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    echo "❌ No active contract for room 1\n";
    exit;
}

$ctrId = $contract['ctr_id'];
echo "✓ Contract ID: $ctrId\n\n";

// Check utility records
$utilCntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM utility WHERE ctr_id = ?');
$utilCntStmt->execute([$ctrId]);
$utilCnt = $utilCntStmt->fetch(PDO::FETCH_ASSOC);
echo "Total utility records: " . $utilCnt['cnt'] . "\n\n";

// Show all utility records
$allUtilStmt = $pdo->prepare('SELECT utl_id, utl_date, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility WHERE ctr_id = ? ORDER BY utl_date ASC');
$allUtilStmt->execute([$ctrId]);
$allUtil = $allUtilStmt->fetchAll(PDO::FETCH_ASSOC);

echo "All utility records:\n";
foreach ($allUtil as $u) {
    echo sprintf("  ID: %d | Date: %s | Water: %d→%d | Elec: %d→%d\n",
        $u['utl_id'], 
        $u['utl_date'],
        $u['utl_water_start'], $u['utl_water_end'],
        $u['utl_elec_start'], $u['utl_elec_end']
    );
}

// Check March 2025 specifically
$marchStmt = $pdo->prepare('SELECT * FROM utility WHERE ctr_id = ? AND YEAR(utl_date) = 2025 AND MONTH(utl_date) = 3');
$marchStmt->execute([$ctrId]);
$march = $marchStmt->fetch(PDO::FETCH_ASSOC);

echo "\nMarch 2025 record: ";
if ($march) {
    echo json_encode($march, JSON_PRETTY_PRINT);
} else {
    echo "NONE\n";
}
