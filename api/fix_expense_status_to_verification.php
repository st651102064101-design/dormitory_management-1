<?php
/**
 * API to fix expense status for payments waiting verification
 * This updates all expenses with status '0' that have pending payment submissions to status '2'
 */
header('Content-Type: application/json; charset=utf-8');
session_start();

// Allow access without authentication for this one-time fix
try {
    require_once __DIR__ . '/ConnectDB.php';
    $pdo = connectDB();
    
    // Update all expenses with status 0 that have pending payment submissions
    $sql = "UPDATE expense SET exp_status = '2'
            WHERE exp_status = '0'
            AND EXISTS (
                SELECT 1 FROM payment 
                WHERE payment.exp_id = expense.exp_id 
                AND payment.pay_status = '0'
                AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'
            )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Fixed $count expense records",
        'details' => "Updated expenses from status '0' (รอชำระเงิน) to '2' (รอตรวจสอบ)",
        'updated_count' => $count
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
