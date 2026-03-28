<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "🧹 Cleanup: Remove Utility Records Without Checkin\n";
echo "==================================================\n\n";

try {
    // Find utility records for contracts without checkin_record
    $findStmt = $pdo->query(
        "SELECT DISTINCT u.ctr_id, c.room_id, COUNT(u.utl_id) as util_count, COUNT(ch.checkin_id) as checkin_count
         FROM utility u
         INNER JOIN contract c ON u.ctr_id = c.ctr_id
         LEFT JOIN checkin_record ch ON u.ctr_id = ch.ctr_id
         GROUP BY u.ctr_id
         HAVING checkin_count = 0"
    );
    
    $problemContracts = $findStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($problemContracts)) {
        echo "✅ No problematic utility records found\n";
        exit;
    }
    
    echo "⚠️  Found " . count($problemContracts) . " contracts with utility but no checkin:\n\n";
    
    $totalDeleted = 0;
    $pdo->beginTransaction();
    
    foreach ($problemContracts as $prob) {
        echo "  • Contract {$prob['ctr_id']} (Room {$prob['room_id']}):\n";
        echo "    - Utility Records: {$prob['util_count']}\n";
        echo "    - Checkin Records: {$prob['checkin_count']}\n";
        
        $deleteStmt = $pdo->prepare("DELETE FROM utility WHERE ctr_id = ?");
        $deleteStmt->execute([$prob['ctr_id']]);
        $deleted = $deleteStmt->rowCount();
        
        echo "    ✓ Deleted $deleted utility records\n\n";
        $totalDeleted += $deleted;
    }
    
    $pdo->commit();
    
    echo "✅ Cleanup Complete!\n";
    echo "   Total Records Deleted: $totalDeleted\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
