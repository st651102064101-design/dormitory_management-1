<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SHOW TABLES LIKE 'signature_logs'");
print_r($stmt->fetchAll());
