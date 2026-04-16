<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 0");
$room_available = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM room WHERE room_status = 1");
$room_occupied = $stmt->fetch()['total'] ?? 0;

echo "room_available = $room_available, room_occupied = $room_occupied\n";

