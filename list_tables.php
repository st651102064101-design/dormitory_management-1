<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . "\n";
}
