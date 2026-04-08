<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
print_r($pdo->query("SELECT * FROM deposit_refund")->fetchAll(PDO::FETCH_ASSOC));
