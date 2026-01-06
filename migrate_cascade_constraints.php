<?php
/**
 * Execute CASCADE DELETE Constraints Migration
 * เพิ่ม ON DELETE CASCADE ให้กับ Foreign Key ทุกตัว
 */

declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔧 Migration: Add CASCADE DELETE Constraints\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// List of all operations
$operations = [
    // Step 1: Drop existing foreign keys
    ['desc' => 'Drop payment FK', 'sql' => "ALTER TABLE `payment` DROP FOREIGN KEY `payment_ibfk_1`", 'ignore_error' => true],
    ['desc' => 'Drop expense FK', 'sql' => "ALTER TABLE `expense` DROP FOREIGN KEY `expense_ibfk_1`", 'ignore_error' => true],
    ['desc' => 'Drop contract FK1', 'sql' => "ALTER TABLE `contract` DROP FOREIGN KEY `contract_ibfk_1`", 'ignore_error' => true],
    ['desc' => 'Drop contract FK2', 'sql' => "ALTER TABLE `contract` DROP FOREIGN KEY `contract_ibfk_2`", 'ignore_error' => true],
    ['desc' => 'Drop booking FK1', 'sql' => "ALTER TABLE `booking` DROP FOREIGN KEY `booking_ibfk_1`", 'ignore_error' => true],
    ['desc' => 'Drop booking FK2', 'sql' => "ALTER TABLE `booking` DROP FOREIGN KEY `booking_ibfk_2`", 'ignore_error' => true],
    
    // Step 2: Add new foreign keys with CASCADE
    [
        'desc' => 'Add booking → room CASCADE',
        'sql' => "ALTER TABLE `booking` ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`) ON DELETE CASCADE ON UPDATE CASCADE"
    ],
    [
        'desc' => 'Add booking → tenant CASCADE',
        'sql' => "ALTER TABLE `booking` ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`tnt_id`) REFERENCES `tenant` (`tnt_id`) ON DELETE CASCADE ON UPDATE CASCADE"
    ],
    [
        'desc' => 'Add contract → tenant CASCADE',
        'sql' => "ALTER TABLE `contract` ADD CONSTRAINT `contract_ibfk_1` FOREIGN KEY (`tnt_id`) REFERENCES `tenant` (`tnt_id`) ON DELETE CASCADE ON UPDATE CASCADE"
    ],
    [
        'desc' => 'Add contract → room CASCADE',
        'sql' => "ALTER TABLE `contract` ADD CONSTRAINT `contract_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`) ON DELETE CASCADE ON UPDATE CASCADE"
    ],
    [
        'desc' => 'Add expense → contract CASCADE',
        'sql' => "ALTER TABLE `expense` ADD CONSTRAINT `expense_ibfk_1` FOREIGN KEY (`ctr_id`) REFERENCES `contract` (`ctr_id`) ON DELETE CASCADE ON UPDATE CASCADE"
    ],
    [
        'desc' => 'Add payment → expense CASCADE',
        'sql' => "ALTER TABLE `payment` ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`exp_id`) REFERENCES `expense` (`exp_id`) ON DELETE CASCADE ON UPDATE CASCADE"
    ],
];

$success = 0;
$failed = 0;

foreach ($operations as $op) {
    echo "➤ {$op['desc']}... ";
    try {
        $pdo->exec($op['sql']);
        echo "✅\n";
        $success++;
    } catch (PDOException $e) {
        if (!empty($op['ignore_error'])) {
            echo "⚠️ (skipped - may not exist)\n";
        } else {
            echo "❌ Error: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Result: {$success} successful, {$failed} failed\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Verify constraints
echo "🔍 ตรวจสอบ Foreign Key Constraints:\n\n";

$stmt = $pdo->query("
    SELECT 
        TABLE_NAME as 'Table',
        CONSTRAINT_NAME as 'FK Name',
        COLUMN_NAME as 'Column',
        REFERENCED_TABLE_NAME as 'Ref Table',
        REFERENCED_COLUMN_NAME as 'Ref Column'
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, CONSTRAINT_NAME
");

$constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($constraints) {
    printf("%-12s %-20s %-12s %-12s %-12s\n", 'Table', 'FK Name', 'Column', 'Ref Table', 'Ref Column');
    echo str_repeat('-', 70) . "\n";
    foreach ($constraints as $c) {
        printf("%-12s %-20s %-12s %-12s %-12s\n", 
            $c['Table'], $c['FK Name'], $c['Column'], $c['Ref Table'], $c['Ref Column']);
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Migration complete!\n";
echo "\n📋 ความสัมพันธ์ CASCADE DELETE:\n";
echo "• ลบ tenant   → booking, contract หายตาม\n";
echo "• ลบ contract → expense หายตาม\n";
echo "• ลบ expense  → payment หายตาม\n";
echo "• ลบ room     → booking, contract หายตาม\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
