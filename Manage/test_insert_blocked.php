<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "🧪 Test: INSERT Still BLOCKED for Duplicate Room\n";
echo "==================================================\n\n";

// ค้นหา contract ที่มี status 0 หรือ 2
$stmt = $pdo->query(
    "SELECT ctr_id, tnt_id, room_id, ctr_status 
     FROM contract 
     WHERE ctr_status IN ('0','2') 
     LIMIT 1"
);
$existingContract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingContract) {
    echo "❌ No active contracts found to test\n";
    exit;
}

echo "📋 Existing Contract:\n";
echo "   Room: {$existingContract['room_id']}, Tenant: {$existingContract['tnt_id']}, Status: {$existingContract['ctr_status']}\n\n";

echo "🔄 Attempting to INSERT new contract for SAME (room + tenant)...\n";
echo "   This should be BLOCKED by trigger\n\n";

try {
    $insertStmt = $pdo->prepare(
        "INSERT INTO contract (tnt_id, room_id, ctr_start, ctr_end, ctr_status, ctr_deposit)
         VALUES (?, ?, ?, ?, '0', 2000)"
    );
    $insertStmt->execute([
        $existingContract['tnt_id'],
        $existingContract['room_id'],
        date('Y-m-d'),
        date('Y-m-d', strtotime('+6 months'))
    ]);
    
    echo "❌ FAILED: INSERT succeeded - Trigger NOT working!\n";
    exit(1);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "✅ SUCCESS: INSERT BLOCKED by trigger!\n";
        echo "   Error: " . $e->getMessage() . "\n\n";
        echo "🎯 Trigger is working correctly!\n";
    } else {
        echo "❌ Unexpected error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
