<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM tenant_workflow WHERE bkg_id = 775954892");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
