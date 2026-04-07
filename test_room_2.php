<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt2 = $pdo->query("SELECT c.room_id, r.room_number, c.ctr_status FROM contract c JOIN room r ON c.room_id = r.room_id WHERE r.room_number = '2'");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
