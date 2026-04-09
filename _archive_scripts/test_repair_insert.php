<?php
session_start();
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

$ctr_id = 1;
$repair_date = '2025-12-05';
$repair_desc = 'ทดสอบการเพิ่มข้อมูล';
$repair_status = '0';

echo "Testing INSERT repair...\n";
echo "ctr_id: $ctr_id\n";
echo "repair_date: $repair_date\n";
echo "repair_desc: $repair_desc\n";
echo "repair_status: $repair_status\n\n";

try {
    $stmt = $pdo->prepare('INSERT INTO repair (ctr_id, repair_date, repair_desc, repair_status) VALUES (?, ?, ?, ?)');
    $result = $stmt->execute([$ctr_id, $repair_date, $repair_desc, $repair_status]);
    
    if ($result) {
        $lastId = $pdo->lastInsertId();
        echo "✅ INSERT success! Last ID: $lastId\n";
    } else {
        echo "❌ INSERT failed!\n";
        echo "Error: " . print_r($stmt->errorInfo(), true);
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

// ตรวจดูว่า record อยู่ DB หรือไม่
echo "\n\nVerifying data in DB:\n";
$check = $pdo->query('SELECT * FROM repair ORDER BY repair_id DESC LIMIT 3;')->fetchAll();
foreach ($check as $row) {
    echo "ID: {$row['repair_id']}, ctr_id: {$row['ctr_id']}, date: {$row['repair_date']}, desc: {$row['repair_desc']}\n";
}
?>
