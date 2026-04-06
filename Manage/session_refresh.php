<?php
declare(strict_types=1);
session_start();

/**
 * Session Refresh Endpoint
 * 
 * Refreshes the session timeout by updating last_activity timestamp.
 * Called by AJAX requests to prevent session timeout during long operations.
 */

header('Content-Type: application/json; charset=UTF-8');

// Update last_activity timestamp to refresh session timeout
if (!empty($_SESSION['admin_username'])) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
}
