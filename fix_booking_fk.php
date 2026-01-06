<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

try {
    $pdo->exec("ALTER TABLE `booking` ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`tnt_id`) REFERENCES `tenant` (`tnt_id`) ON DELETE CASCADE ON UPDATE CASCADE");
    echo "✅ เพิ่ม booking → tenant CASCADE สำเร็จ!\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// แสดง FK ทั้งหมด
$stmt = $pdo->query("
    SELECT 
        TABLE_NAME as tbl,
        CONSTRAINT_NAME as fk_name,
        COLUMN_NAME as col,
        REFERENCED_TABLE_NAME as ref_tbl
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME
");

echo "\n📋 Foreign Key Constraints ทั้งหมด:\n";
foreach ($stmt as $row) {
    echo "  {$row['tbl']}.{$row['col']} → {$row['ref_tbl']} ({$row['fk_name']})\n";
}
