<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT room_number, room_status FROM room WHERE room_number IN ('1', '2', '3')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
