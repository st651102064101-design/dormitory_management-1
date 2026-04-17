<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT DISTINCT ctr_status FROM contract");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
