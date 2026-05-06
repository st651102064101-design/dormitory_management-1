<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
// Find the rejected payments for exp_id 777608161
$stmt = $pdo->query("SELECT * FROM payment WHERE exp_id = 777608161");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
