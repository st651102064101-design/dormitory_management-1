<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT bkg_status FROM booking WHERE bkg_id = 775954892");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
