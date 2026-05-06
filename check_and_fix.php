<?php
declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

// Find expenses with status 0 that have pending payments
$stmt = $pdo->prepare("
    SELECT DISTINCT e.exp_id, e.exp_status, e.exp_month, c.ctr_id
    FROM expense e
    JOIN contract c ON e.ctr_id = c.ctr_id
    WHERE e.exp_status = '0'
    AND EXISTS (
        SELECT 1 FROM payment p 
        WHERE p.exp_id = e.exp_id 
        AND p.pay_status = '0'
        AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
    )
    LIMIT 10
");
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($expenses) . " expenses to update:\n";
foreach ($expenses as $exp) {
    echo "Exp ID: " . $exp['exp_id'] . ", CTR: " . $exp['ctr_id'] . ", Month: " . $exp['exp_month'] . "\n";
}

// Now update them
$updateStmt = $pdo->prepare("
    UPDATE expense SET exp_status = '2'
    WHERE exp_status = '0'
    AND EXISTS (
        SELECT 1 FROM payment p 
        WHERE p.exp_id = expense.exp_id 
        AND p.pay_status = '0'
        AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
    )
");
$updateStmt->execute();

echo "\nUpdated " . $updateStmt->rowCount() . " records.\n";
?>
