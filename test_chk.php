<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM checkout_record WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE room_id IN (2,3))");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
