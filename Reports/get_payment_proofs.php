<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    // Check authentication
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized');
    }

    // Get expense ID
    $expenseId = isset($_GET['exp_id']) ? (int)$_GET['exp_id'] : 0;
    
    if ($expenseId <= 0) {
        throw new Exception('Invalid expense ID');
    }

    // Connect to database using ConnectDB
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();

    // Get payment records with proof files
    $stmt = $pdo->prepare("
        SELECT 
            p.pay_id,
            p.pay_amount,
            p.pay_proof,
            p.pay_status,
            p.pay_date
        FROM payment p
        WHERE p.exp_id = ?
        ORDER BY p.pay_date DESC, p.pay_id DESC
    ");
    
    $stmt->execute([$expenseId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response
    echo json_encode([
        'success' => true,
        'payments' => $payments
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
