<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SHOW CREATE TABLE room");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
