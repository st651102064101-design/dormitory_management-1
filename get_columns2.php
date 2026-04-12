<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SHOW COLUMNS FROM booking");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
