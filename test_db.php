<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("DESCRIBE admin");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
