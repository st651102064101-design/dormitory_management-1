<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

// ตรวจสอบว่ามี column effective_date หรือยัง
$cols = $pdo->query("SHOW COLUMNS FROM rate LIKE 'effective_date'")->fetch();
if (!$cols) {
    $pdo->exec("ALTER TABLE rate ADD COLUMN effective_date DATE DEFAULT NULL");
    echo "Added effective_date column\n";
}

$cols2 = $pdo->query("SHOW COLUMNS FROM rate LIKE 'created_at'")->fetch();
if (!$cols2) {
    $pdo->exec("ALTER TABLE rate ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "Added created_at column\n";
}

// อัพเดท record เดิม
$pdo->exec("UPDATE rate SET effective_date = '2025-01-01' WHERE effective_date IS NULL");

echo "=== Rate Table Structure ===\n";
$stmt = $pdo->query("DESCRIBE rate");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Current Rates ===\n";
$stmt = $pdo->query("SELECT * FROM rate ORDER BY rate_id DESC");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
