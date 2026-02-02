<?php
session_start();
require_once __DIR__ . '/ConnectDB.php';

// Set error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log file
$logFile = __DIR__ . '/google_callback_debug.log';

// Start logging
file_put_contents($logFile, "=== Google Callback Debug " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);

try {
    file_put_contents($logFile, "GET params: " . json_encode($_GET) . "\n", FILE_APPEND);
    file_put_contents($logFile, "SESSION: " . json_encode($_SESSION) . "\n", FILE_APPEND);
    
    // Test database connection
    $pdo = connectDB();
    file_put_contents($logFile, "✓ Database connected\n", FILE_APPEND);
    
    // Test query
    $stmt = $pdo->prepare('SELECT 1');
    $stmt->execute();
    file_put_contents($logFile, "✓ Query executed\n", FILE_APPEND);
    
    // Get tenant T177001439848
    $stmt = $pdo->prepare('SELECT bkg_id FROM booking WHERE tnt_id = ? AND bkg_status IN ("1","2") LIMIT 1');
    $stmt->execute(['T177001439848']);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents($logFile, "Booking for T177001439848: " . json_encode($booking) . "\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
}

// Read and display log
echo '<pre>';
echo file_get_contents($logFile);
echo '</pre>';
?>
