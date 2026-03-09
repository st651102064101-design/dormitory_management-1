<?php
declare(strict_types=1);
header('Content-Type: application/json');
session_start();

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();

    $stmt = $pdo->query("
        SELECT rate_water, rate_elec 
        FROM rate 
        ORDER BY effective_date DESC, rate_id DESC 
        LIMIT 1
    ");

    $rate = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rate) {
        echo json_encode([
            'rate_water' => (float)$rate['rate_water'],
            'rate_elec' => (float)$rate['rate_elec']
        ]);
    } else {
        // ค่า default ถ้าไม่มีข้อมูล
        echo json_encode([
            'rate_water' => 18.0,
            'rate_elec' => 8.0
        ]);
    }
    
} catch (Throwable $e) {
    // Log the exception for debugging but do not propagate 500 to client
    error_log('[get_latest_rate] ' . $e->getMessage());
    // return defaults and indicate failure so caller can fallback elegantly
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'rate_water' => 18.0,
        'rate_elec' => 8.0
    ]);
}
