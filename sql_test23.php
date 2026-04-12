<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM booking WHERE bkg_status = '5'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
