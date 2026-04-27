<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT bp_proof FROM booking_payment WHERE bp_proof IS NOT NULL AND bp_proof != '' LIMIT 10");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
