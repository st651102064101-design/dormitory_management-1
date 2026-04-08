<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
print_r($pdo->query("SELECT * FROM deposit_refund WHERE refund_status = '0'")->fetchAll(PDO::FETCH_ASSOC));
