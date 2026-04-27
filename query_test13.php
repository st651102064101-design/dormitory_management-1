<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT * FROM tenant WHERE tnt_id IN (SELECT tnt_id FROM booking WHERE bkg_id IN (777295620, 777295396, 776340328))");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
