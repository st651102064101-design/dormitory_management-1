<?php
require 'ConnectDB.php';
$pdo = connectDB();
$db = $pdo->query("SELECT DATABASE()")->fetchColumn();
$stmt = $pdo->prepare("SELECT TABLE_NAME, REFERENCED_TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IN ('deposit_refund', 'repair', 'termination') AND TABLE_SCHEMA = ?");
$stmt->execute([$db]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
