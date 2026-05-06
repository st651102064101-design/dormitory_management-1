<?php
/**
 * Fix script to update expense status for existing pending payments
 */
declare(strict_types=1);

require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    // Find all expenses with status '0' that have pending payments (pay_status = '0')
    // and update them to status '2' (Pending Verification)
    $stmt = $pdo->prepare("
        UPDATE expense SET exp_status = '2'
        WHERE exp_status = '0'
        AND EXISTS (
            SELECT 1 FROM payment p 
            WHERE p.exp_id = expense.exp_id 
            AND p.pay_status = '0'
            AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
        )
    ");
    
    $stmt->execute();
    $rowCount = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Updated $rowCount expense records from status '0' to '2'",
        'affected_rows' => $rowCount
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
