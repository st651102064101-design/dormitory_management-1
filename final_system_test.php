<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Final System Test - Meter Schedule Implementation\n";
echo "================================================\n\n";

// Test 1: Verify all settings exist
echo "TEST 1: System Settings\n";
$requiredSettings = [
    'meter_reading_day_start' => '20',
    'meter_reading_day_end' => '25',
    'billing_cycle_type' => 'calendar_month'
];

$allSettingsOk = true;
foreach ($requiredSettings as $key => $expectedValue) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['setting_value'] === $expectedValue) {
        echo "  ✅ $key = {$result['setting_value']}\n";
    } else {
        echo "  ❌ $key MISSING or WRONG\n";
        $allSettingsOk = false;
    }
}

// Test 2: Verify meter_schedule table
echo "\nTEST 2: Database Structure\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'meter_schedule'");
    $table = $stmt->fetch();
    if ($table) {
        echo "  ✅ meter_schedule table EXISTS\n";
        
        // Check columns
        $colStmt = $pdo->query("SHOW COLUMNS FROM meter_schedule");
        $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredCols = ['schedule_id', 'schedule_month', 'schedule_year', 'reading_day_start', 'reading_day_end'];
        foreach ($requiredCols as $col) {
            if (in_array($col, $columns)) {
                echo "    ✅ Column: $col\n";
            } else {
                echo "    ❌ Missing column: $col\n";
                $allSettingsOk = false;
            }
        }
    } else {
        echo "  ❌ meter_schedule table NOT FOUND\n";
        $allSettingsOk = false;
    }
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n";
    $allSettingsOk = false;
}

// Test 3: Test meter blocking logic
echo "\nTEST 3: Meter Blocking Logic\n";
$currentDay = (int)date('d');
$readingDayStart = 20;
$readingDayEnd = 25;

$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'meter_reading_day_start'");
$setting = $stmt->fetch();
if ($setting) $readingDayStart = (int)$setting['setting_value'];

$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'meter_reading_day_end'");
$setting = $stmt->fetch();
if ($setting) $readingDayEnd = (int)$setting['setting_value'];

$outsideMeterWindow = ($currentDay < $readingDayStart || $currentDay > $readingDayEnd);

echo "  Current Day: $currentDay\n";
echo "  Window: $readingDayStart-$readingDayEnd\n";
if ($outsideMeterWindow) {
    echo "  ❌ METER BLOCKED (Outside window)\n";
    if ($currentDay < $readingDayStart) {
        echo "     Message: รอถึงวันที่ $readingDayStart เพื่อจดมิเตอร์\n";
    } else {
        echo "     Message: หมดระยะเวลาจดมิเตอร์ (รอเดือนหน้า)\n";
    }
} else {
    echo "  ✅ METER ALLOWED (Inside window)\n";
}

// Test 4: Room 3 Status
echo "\nTEST 4: Room 3 Status Check\n";
$stmt = $pdo->prepare("
    SELECT c.ctr_id, c.room_id, c.ctr_start, c.ctr_end,
           (SELECT COUNT(*) FROM checkin_record WHERE ctr_id = c.ctr_id) as checkin_count,
           tw.current_step
    FROM contract c
    LEFT JOIN tenant_workflow tw ON c.ctr_id = tw.ctr_id
    WHERE c.room_id = 3 AND c.ctr_status = '0'
");
$stmt->execute();
$room3 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($room3) {
    echo "  Contract: {$room3['ctr_id']}\n";
    echo "  Period: {$room3['ctr_start']} to {$room3['ctr_end']}\n";
    echo "  Step: {$room3['current_step']}/5\n";
    echo "  Checkin Records: {$room3['checkin_count']}\n";
    
    $meterBlocked = ($room3['current_step'] <= 3) || ($room3['current_step'] === 4 && $room3['checkin_count'] === 0) || $outsideMeterWindow;
    
    echo "  Meter Status: " . ($meterBlocked ? "❌ BLOCKED\n" : "✅ ALLOWED\n");
    
    if ($meterBlocked) {
        if ($room3['current_step'] < 4) {
            echo "    Reason: Step {$room3['current_step']}/5 (Need Step 4+)\n";
        } elseif ($room3['checkin_count'] === 0) {
            echo "    Reason: No checkin_record\n";
        } elseif ($outsideMeterWindow) {
            echo "    Reason: Outside meter window (Day $currentDay, Window $readingDayStart-$readingDayEnd)\n";
        }
    }
} else {
    echo "  No active contract found\n";
}

// Test 5: Verify no utility records before contract start
echo "\nTEST 5: Data Integrity\n";
$stmt = $pdo->prepare("
    SELECT u.ctr_id, u.utl_date, c.ctr_start, c.room_id
    FROM utility u
    INNER JOIN contract c ON u.ctr_id = c.ctr_id
    WHERE c.ctr_status = '0'
    AND (YEAR(u.utl_date) < YEAR(c.ctr_start) OR 
         (YEAR(u.utl_date) = YEAR(c.ctr_start) AND MONTH(u.utl_date) < MONTH(c.ctr_start)))
");
$stmt->execute();
$earlyUtils = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($earlyUtils)) {
    echo "  ✅ No utility records before contract start dates\n";
} else {
    echo "  ❌ Found " . count($earlyUtils) . " utility records before contract start:\n";
    foreach ($earlyUtils as $u) {
        echo "     Room: {$u['room_id']}, Util Date: {$u['utl_date']}, Contract Start: {$u['ctr_start']}\n";
    }
    $allSettingsOk = false;
}

// Final Summary
echo "\n" . str_repeat("=", 50) . "\n";
if ($allSettingsOk) {
    echo "✅ ALL TESTS PASSED\n";
    echo "Meter Schedule System is READY for production\n";
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    echo "Please review errors above\n";
}
echo str_repeat("=", 50) . "\n";
?>
