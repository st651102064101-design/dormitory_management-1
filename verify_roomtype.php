<?php
require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    // Check if table exists and get data
    $stmt = $pdo->query("SELECT * FROM roomtype ORDER BY type_id");
    $roomtypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'table_exists' => true,
        'record_count' => count($roomtypes),
        'data' => $roomtypes
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
