<?php
/**
 * Setup Google OAuth State Table
 * Creates the google_oauth_state table for storing temporary OAuth state tokens
 * Run this once to set up the required database table
 */

require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    // Create google_oauth_state table if it doesn't exist
    $sql = '
        CREATE TABLE IF NOT EXISTS google_oauth_state (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state VARCHAR(255) NOT NULL UNIQUE,
            admin_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_state (state),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ';
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'google_oauth_state table created successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error creating table: ' . $e->getMessage()
    ]);
}
?>
