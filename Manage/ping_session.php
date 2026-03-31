<?php
/**
 * ping_session.php
 * รีเซ็ต last_activity และคืน remaining_ms ให้ client-side countdown
 * เรียกผ่าน AJAX (POST) จาก sidebar.php เมื่อผู้ใช้มีกิจกรรม
 */
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['expired' => true, 'remaining_ms' => 0]);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';
// connectDB() also runs the session timeout check internally.
// If the session was already expired it will have exited above.
// If we reach here the session is still valid — last_activity was just refreshed.

// Read timeout for returning remaining_ms
$timeoutMin = isset($_SESSION['_timeout_min']) ? (int)$_SESSION['_timeout_min'] : 30;
$remainingMs = ($timeoutMin * 60) * 1000; // freshly reset, full duration remaining

echo json_encode([
    'success'      => true,
    'expired'      => false,
    'remaining_ms' => $remainingMs,
    'timeout_min'  => $timeoutMin,
]);
