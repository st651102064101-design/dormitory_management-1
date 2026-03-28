<?php
require 'ConnectDB.php';
$pdo = connectDB();

echo "Setting up Meter Reading Schedule System\n";
echo "========================================\n\n";

try {
    // 1. Create meter_schedule table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS meter_schedule (
        schedule_id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_month INT NOT NULL COMMENT 'Month (1-12)',
        schedule_year INT NOT NULL COMMENT 'Year',
        reading_day_start INT DEFAULT 20 COMMENT 'Day to start reading',
        reading_day_end INT DEFAULT 25 COMMENT 'Day to end reading',
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_month_year (schedule_month, schedule_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($createTableSQL);
    echo "✅ Table meter_schedule created\n";
    
    // 2. Add system settings if not exist
    $settings = [
        ['meter_reading_day_start', '20'],
        ['meter_reading_day_end', '25'],
        ['billing_cycle_type', 'calendar_month'],
        ['billing_cycle_description', 'Calendar Month (20-25 of each month)']
    ];
    
    foreach ($settings as $setting) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM system_settings WHERE setting_key = ?");
        $checkStmt->execute([$setting[0]]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0;
        
        if (!$exists) {
            $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $insertStmt->execute($setting);
            echo "✅ Setting: {$setting[0]} = {$setting[1]}\n";
        } else {
            echo "⏭️  Setting already exists: {$setting[0]}\n";
        }
    }
    
    echo "\n✅ Setup Complete!\n\n";
    echo "Meter Reading Window Configuration:\n";
    echo "  - Days: 20-25 of each month\n";
    echo "  - Billing Cycle: Calendar Month\n";
    echo "  - Rooms can record meter readings only during this window\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Example usage of the settings
$readingDayStart = 20;
$readingDayEnd = 25;
$currentDay = date('d');
$workflowStep = 4;

echo "Reading Day Start: {$readingDayStart}\n";
echo "Reading Day End: {$readingDayEnd}\n";
echo "Current Day: {$currentDay}\n";
echo "Workflow Step: {$workflowStep}\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window Configuration:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Meter Reading Window:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this window\n";

echo "Status:\n";
echo "  - Days: 20-25 of each month\n";
echo "  - Billing Cycle: Calendar Month\n";
echo "  - Rooms can record meter readings only during this