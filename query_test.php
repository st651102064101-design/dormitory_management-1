<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT bkg_status, tnt_id, room_id FROM booking WHERE bkg_id = ?");
$stmt->execute(['777295620']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
$stmt = $pdo->prepare("SELECT * FROM tenant_workflow WHERE bkg_id = ?");
$stmt->execute(['777295620']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
