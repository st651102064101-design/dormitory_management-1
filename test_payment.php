<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT p.*, e.exp_month FROM payment p JOIN expense e ON p.exp_id = e.exp_id WHERE e.ctr_id = 11");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
