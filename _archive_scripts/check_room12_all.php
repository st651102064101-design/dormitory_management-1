<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "All Contracts for Room 12:\n";
$stmt = $pdo->query("SELECT * FROM contract WHERE room_id = 12");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  CTR {$r['ctr_id']}: {$r['ctr_start']} to {$r['ctr_end']} (status: {$r['ctr_status']})\n";
}

echo "\nUtility records:\n";
$stmt = $pdo->query("SELECT * FROM utility WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE room_id = 12)");
$utils = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total: " . count($utils) . "\n";
foreach ($utils as $u) {
    echo "  {$u['utl_date']}: {$u['utl_water_start']} → {$u['utl_water_end']}\n";
}
?>
