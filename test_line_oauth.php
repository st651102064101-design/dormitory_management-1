<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT DISTINCT provider FROM tenant_oauth");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
