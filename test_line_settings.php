<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'line_%'");
print_r($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
