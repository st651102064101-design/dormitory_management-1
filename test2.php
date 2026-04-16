<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$sql = "SELECT COUNT(*) as total FROM room WHERE room_status = '1'";
echo "room_status=1 -> " . $pdo->query($sql)->fetchColumn() . "\n";

$sql = "SELECT room_id FROM room WHERE room_status = '1'";
print_r($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
