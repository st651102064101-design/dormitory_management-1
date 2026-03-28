<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Meter Schedule System - Verification\n";
echo "====================================\n\n";

// 1. Check settings
echo "1. System Settings:\n";
$settings = ['meter_reading_day_start', 'meter_reading_day_end', 'billing_cycle_type'];
foreach ($settings as $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "   ✅ {$key} = {$result['setting_value']}\n";
    } else {
        echo "   ❌ {$key} NOT FOUND\n";
    }
}

// 2. Check meter_schedule table
echo "\n2. Database Tables:\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'meter_schedule'");
    if ($stmt->fetch()) {
        echo "   ✅ meter_schedule table exists\n";
    } else {
        echo "   ❌ meter_schedule table NOT found\n";
    }
} catch (PDOException $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 3. Check current date status
echo "\n3. Current Date Status (Today " . date('Y-m-d') . "):\n";
$currentDay = (int)date('d');
$readingDayStart = 20;
$readingDayEnd = 25;

$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'meter_reading_day_start'");
$setting = $stmt->fetch();
if ($setting) $readingDayStart = (int)$setting['setting_value'];

$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'meter_reading_day_end'");
$setting = $stmt->fetch();
if ($setting) $readingDayEnd = (int)$setting['setting_value'];

$isInWindow = ($currentDay >= $readingDayStart && $currentDay <= $readingDayEnd);
echo "   Current Day: $currentDay\n";
echo "   Reading Window: $readingDayStart-$readingDayEnd\n";
echo "   Status: " . ($isInWindow ? "✅ METER READING ALLOWED" : "❌ METER READING BLOCKED") . "\n";

if (!$isInWindow) {
    if ($currentDay < $readingDayStart) {
        $daysUntil = $readingDayStart - $currentDay;
        echo "   Message: รอถึงวันที่ $readingDayStart เพื่อจดมิเตอร์ ($daysUntil วัน)\n";
    } else {
        $daysUntilNext = ($readingDayStart + 31) - $currentDay;
        echo "   Message: หมดระยะเวลาจดมิเตอร์ (รอเดือนหน้า ~$daysUntilNext วัน)\n";
    }
}

echo "\n✅ Verification Complete!\n";
?>
