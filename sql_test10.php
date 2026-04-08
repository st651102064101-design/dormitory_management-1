<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
print_r($pdo->query("SHOW COLUMNS FROM contract")->fetchAll(PDO::FETCH_ASSOC));
