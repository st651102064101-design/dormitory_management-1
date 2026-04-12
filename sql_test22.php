<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM contract WHERE tnt_id = 1775444378");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SELECT * FROM utility WHERE ctr_id = 775954893");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SELECT * FROM expense WHERE ctr_id = 775954893");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
