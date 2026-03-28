<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "🔍 Finding ACTIVE contracts (status 0 or 2)...\n\n";

$stmt = $pdo->query("SELECT tnt_id, room_id, ctr_id, ctr_status FROM contract WHERE ctr_status IN ('0','2') LIMIT 1");
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    echo "❌ No ACTIVE contracts found to test\n\n";
    echo "Available contracts (first 5):\n";
    $allStmt = $pdo->query("SELECT ctr_id, tnt_id, room_id, ctr_status FROM contract LIMIT 5");
    foreach ($allStmt as $row) {
        echo "  ID: {$row['ctr_id']}, Status: {$row['ctr_status']}, Tenant: {$row['tnt_id']}, Room: {$row['room_id']}\n";
    }
    echo "\nNote: Trigger only prevents duplicates for status 0 or 2 (active contracts)\n";
    exit;
}

echo "✅ Found ACTIVE contract:\n";
echo "   ID: {$contract['ctr_id']}, Status: {$contract['ctr_status']}\n";
echo "   Tenant: {$contract['tnt_id']}, Room: {$contract['room_id']}\n\n";

echo "🔄 Testing: Attempting to INSERT duplicate...\n\n";

try {
    $insert = $pdo->prepare(
        "INSERT INTO contract (tnt_id, room_id, ctr_start, ctr_end, ctr_status, ctr_deposit) 
         VALUES (?, ?, ?, ?, '0', 0)"
    );
    $insert->execute([
        $contract['tnt_id'],
        $contract['room_id'],
        date('Y-m-d'),
        date('Y-m-d', strtotime('+6 months'))
    ]);
    
    echo "❌ FAILED: Insert succeeded - Trigger NOT working!\n";
    
} catch (PDOException $e) {
    echo "✅ SUCCESS: Trigger BLOCKED!!\n";
    echo "Error Message: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'Duplicate: Room') !== false) {
        echo "📝 Type: Room duplicate\n";
    } elseif (strpos($e->getMessage(), 'Duplicate: Tenant') !== false) {
        echo "📝 Type: Tenant duplicate\n";
    }
    
    echo "\n✅ Trigger is working perfectly!\n";
}
?>
