<?php
require 'ConnectDB.php';
$pdo = connectDB();
$sql = "SELECT * FROM expense_detail WHERE exp_id = 775208035";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
