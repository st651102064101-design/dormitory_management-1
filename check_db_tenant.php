<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT * FROM tenant WHERE tnt_phone = '0980102587'");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->prepare("SELECT * FROM booking WHERE tnt_id IN (SELECT tnt_id FROM tenant WHERE tnt_phone = '0980102587')");
$stmt2->execute();
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

$stmt3 = $pdo->prepare("SELECT * FROM contract WHERE tnt_id IN (SELECT tnt_id FROM tenant WHERE tnt_phone = '0980102587')");
$stmt3->execute();
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
