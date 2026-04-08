<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT room_number FROM room r WHERE r.room_id=1");
echo $stmt->fetchColumn() . "\n";
