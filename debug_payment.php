<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM payment ORDER BY pay_id DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
