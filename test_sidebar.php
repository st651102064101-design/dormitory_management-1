<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
// define functions used
function thaiMonthYearLong($m) { return $m; }
$settings = ['site_name' => 'Test'];
$currentPage = 'test.php';

try {
    ob_start();
    include 'includes/sidebar.php';
    ob_end_clean();
    echo "SUCCESS\n";
    echo "wizardIncompleteCount: " . $wizardIncompleteCount . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
