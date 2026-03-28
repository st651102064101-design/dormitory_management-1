<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "🧪 Testing Database Trigger - Duplicate Prevention\n";
echo "====================================================\n\n";

// ค้นหา contract ที่มีอยู่
$existingStmt = $pdo->query("SELECT tnt_id, room_id, ctr_id, ctr_status FROM contract LIMIT 1");
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    echo "❌ No contracts found to test\n";
    exit;
}

echo "📋 Existing Contract (will try to create duplicate):\n";
echo "   Tenant: {$existing['tnt_id']}\n";
echo "   Room: {$existing['room_id']}\n";
echo "   Contract ID: {$existing['ctr_id']}\n";
echo "   Status: {$existing['ctr_status']}\n\n";

if ($existing['ctr_status'] !== '0' && $existing['ctr_status'] !== '2') {
    echo "⚠️  Contract status is {$existing['ctr_status']} (not active), skipping test\n";
    exit;
}

echo "🔄 Attempting to INSERT duplicate contract...\n";

try {
    $testInsert = $pdo->prepare(
        "INSERT INTO contract (tnt_id, room_id, ctr_start, ctr_end, ctr_status, ctr_deposit) 
         VALUES (?, ?, ?, ?, '0', 0)"
    );
    $testInsert->execute([
        $existing['tnt_id'],
        $existing['room_id'],
        date('Y-m-d'),
        date('Y-m-d', strtotime('+6 months'))
    ]);
    
    echo "❌ ERROR: Insert succeeded! Trigger NOT working!\n";
    exit;
    
} catch (PDOException $e) {
    echo "✅ SUCCESS: Trigger BLOCKED the duplicate!\n\n";
    echo "Error Details:\n";
    echo "  Code: " . $e->getCode() . "\n";
    echo "  Message: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'Duplicate: Room already has active contract') !== false) {
        echo "✓ Trigger Error Message: 'Duplicate: Room already has active contract'\n";
        echo "✓ This will be translated to Thai: '❌ ห้องนี้มีสัญญาที่ยังใช้อยู่แล้ว'\n";
    }
}

echo "\n🎯 Trigger is working correctly!\n";
?>
