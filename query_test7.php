<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT * FROM tenant_workflow WHERE bkg_id = ?");
$stmt->execute(['777295620']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
