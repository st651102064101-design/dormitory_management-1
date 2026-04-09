<?php
require 'ConnectDB.php';
$pdo = connectDB();
$ids = [775697436, 775695448, 775619508, 775395728, 774529248];
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT b.*, t.tnt_name, t.tnt_status, c.ctr_status FROM booking b LEFT JOIN tenant t ON b.tnt_id = t.tnt_id LEFT JOIN contract c ON t.tnt_id = c.tnt_id WHERE b.bkg_id IN ($placeholders)");
$stmt->execute($ids);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
