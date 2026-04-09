<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM booking_payment ORDER BY bp_id DESC LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
