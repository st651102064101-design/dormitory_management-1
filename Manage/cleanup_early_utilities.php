<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Cleanup: Remove Utility Records Before Billing Start\n";
echo "=====================================================\n\n";

try {
    // Find utility records that are before the contract's billing start month
    $stmt = $pdo->query("
        SELECT u.utl_id, u.ctr_id, u.utl_date, c.ctr_start, c.room_id
        FROM utility u
        INNER JOIN contract c ON u.ctr_id = c.ctr_id
        WHERE c.ctr_status = '0'
        AND (YEAR(u.utl_date) < YEAR(c.ctr_start) OR (YEAR(u.utl_date) = YEAR(c.ctr_start) AND MONTH(u.utl_date) < MONTH(c.ctr_start)))
    ");
    
    $problematic = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($problematic)) {
        echo "✅ No problematic utility records found\n";
    } else {
        echo "⚠️  Found " . count($problematic) . " utility records before billing start:\n\n";
        
        $pdo->beginTransaction();
        $deleted = 0;
        
        foreach ($problematic as $u) {
            echo "  • Room {$u['room_id']}, CTR {$u['ctr_id']}\n";
            echo "    Utility Date: {$u['utl_date']}\n";
            echo "    Contract Start: {$u['ctr_start']}\n";
            
            $delStmt = $pdo->prepare("DELETE FROM utility WHERE utl_id = ?");
            $delStmt->execute([$u['utl_id']]);
            $deleted += $delStmt->rowCount();
            echo "    ✓ Deleted\n\n";
        }
        
        $pdo->commit();
        echo "✅ Cleanup Complete!\n";
        echo "   Total Records Deleted: $deleted\n";
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
