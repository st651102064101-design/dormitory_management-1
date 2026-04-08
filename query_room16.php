<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->prepare("SELECT * FROM room WHERE room_number = '16'");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$room_id = '77';
$stmt = $pdo->prepare("SELECT * FROM room WHERE room_id = ?");
$stmt->execute([$room_id]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
