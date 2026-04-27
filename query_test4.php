<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT bp_id, bkg_id, bp_amount, bp_proof FROM booking_payment WHERE bkg_id = ?");
$stmt->execute(['777295620']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
