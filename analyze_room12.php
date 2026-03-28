<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Room 12 Contract Analysis:\n";
echo "===========================\n\n";

// Step 1: All contracts for room 12
echo "1. All contracts for room 12:\n";
$stmt = $pdo->query("SELECT ctr_id, ctr_start, ctr_end, ctr_status FROM contract WHERE room_id = 12 ORDER BY ctr_id DESC LIMIT 10");
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Count: " . count($contracts) . "\n";
foreach ($contracts as $c) {
    echo "   CTR {$c['ctr_id']}: {$c['ctr_start']} to {$c['ctr_end']}, Status: {$c['ctr_status']}\n";
}

// Step 2: Active contracts for room 12
echo "\n2. Active contracts (ctr_status=0) for room 12:\n";
$stmt = $pdo->query("SELECT ctr_id, ctr_start, ctr_end FROM contract WHERE room_id = 12 AND ctr_status = '0'");
$active = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Count: " . count($active) . "\n";
foreach ($active as $c) {
    echo "   CTR {$c['ctr_id']}: {$c['ctr_start']} to {$c['ctr_end']}\n";
}

// Step 3: MAX ctr_id for room 12 active
echo "\n3. MAX(  ctr_id) for room 12 where ctr_status=0:\n";
$stmt = $pdo->query("SELECT MAX(ctr_id) as max_ctr FROM contract WHERE room_id = 12 AND ctr_status = '0'");
$max = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   MAX: " . ($max['max_ctr'] ?? 'NULL') . "\n";
?>
