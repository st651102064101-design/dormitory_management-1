<?php
ob_start();
session_start();
ob_clean();

header('Content-Type: application/json; charset=utf-8');

try {
    // Test ConnectDB
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();
    
    if (!$pdo) {
        throw new Exception('PDO connection failed');
    }
    
    // Test database query
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful',
        'database' => $dbName
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
