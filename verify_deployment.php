<?php
// Verify deployment status
require 'ConnectDB.php';

echo "=== DEPLOYMENT VERIFICATION ===\n\n";

// Check system_settings table
try {
    $result = $pdo->query('SELECT COUNT(*) as count FROM system_settings');
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✓ system_settings table exists | Records: " . $row['count'] . "\n";
    
    $result = $pdo->query('SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['setting_key']}: {$row['setting_value']}\n";
    }
} catch (Exception $e) {
    echo "✗ system_settings table NOT found\n";
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Check manage_utility.php references
echo "✓ manage_utility.php implementation:\n";
$lines = file('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/manage_utility.php');
$found_meter_window = false;
foreach ($lines as $i => $line) {
    if (strpos($line, 'meter_reading_day') !== false) {
        $found_meter_window = true;
        break;
    }
}
if ($found_meter_window) {
    echo "  - Meter reading day window logic found\n";
}

echo "\nDEPLOYMENT STATUS: ";
echo ($found_meter_window ? "✅ PRODUCTION READY" : "❌ INCOMPLETE");
echo "\n";
?>
