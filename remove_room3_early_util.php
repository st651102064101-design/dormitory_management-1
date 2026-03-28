<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Remove Early Utility from Room 3\n";
echo "=================================\n\n";

try {
    // Room 3 Contract: starts 03-09-2026 (billing month = 03)
    // But user says billing should start 04-2026
    // Remove utility from 03-29 (pre-billing)
    
    $delStmt = $pdo->prepare("
        DELETE FROM utility
        WHERE ctr_id = (SELECT ctr_id FROM contract WHERE room_id = 3 AND ctr_status = '0')
        AND utl_date = '2026-03-29'
    ");
    $delStmt->execute();
    $deleted = $delStmt->rowCount();
    
    echo "Deleted $deleted utility record(s) from Room 3 (2026-03-29)\n\n";
    
    // Verify
    $stmt = $pdo->prepare("
        SELECT u.utl_id, u.utl_date
        FROM utility u
        WHERE u.ctr_id = (SELECT ctr_id FROM contract WHERE room_id = 3 AND ctr_status = '0')
        ORDER BY u.utl_date DESC
    ");
    $stmt->execute();
    $remaining = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Remaining utilities for Room 3:\n";
    if (empty($remaining)) {
        echo "  None\n";
    } else {
        foreach ($remaining as $u) {
            echo "  {$u['utl_date']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
