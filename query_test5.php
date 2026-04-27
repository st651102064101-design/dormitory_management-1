<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT pay_id, pay_proof FROM payment WHERE exp_id IN (SELECT exp_id FROM expense WHERE ctr_id = (SELECT ctr_id FROM contract WHERE bkg_id = ?))");
$stmt->execute(['777295620']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
