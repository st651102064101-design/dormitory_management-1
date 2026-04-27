<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT * FROM contract WHERE ctr_id = ?");
$stmt->execute(['777295621']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
$stmt = $pdo->prepare("SELECT * FROM expense WHERE ctr_id = ?");
$stmt->execute(['777295621']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
