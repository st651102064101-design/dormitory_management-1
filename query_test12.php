<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT * FROM booking_payment WHERE bkg_id IN (777295620, 777295396, 776340328)");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
