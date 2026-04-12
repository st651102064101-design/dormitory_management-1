<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SHOW COLUMNS FROM booking LIKE 'bkg_status'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
