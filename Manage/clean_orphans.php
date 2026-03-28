<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "🧹 Cleaning orphaned tenant_workflow records...\n";

// Delete orphaned records
$stmt = $pdo->prepare("DELETE FROM tenant_workflow  
WHERE ctr_id IS NULL OR ctr_id NOT IN (SELECT ctr_id FROM contract)");
$stmt->execute();
$deleted = $stmt->rowCount();

if ($deleted > 0) {
    echo "✓ Deleted $deleted orphaned records\n";
} else {
    echo "✓ No orphaned records found\n";
}
?>
