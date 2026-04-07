<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT room_number, room_status FROM room");
$all = $stmt->fetchAll(PDO::FETCH_ASSOC);
$buggy = [];
foreach($all as $r) {
    if ($r['room_status'] == '1') $buggy[] = $r['room_number'];
}
echo "Rooms marked occupied in DB col: " . implode(',', $buggy);
