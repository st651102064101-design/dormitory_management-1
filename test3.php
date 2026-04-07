<?php
require 'ConnectDB.php';
$pdo = connectDB();

echo "Contracts:\n";
$stmt = $pdo->query("SELECT ctr_status, count(*) FROM contract GROUP BY ctr_status");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "Bookings:\n";
$stmt = $pdo->query("SELECT bkg_status, count(*) FROM booking GROUP BY bkg_status");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "Rooms 1, 3, 5:\n";
$stmt = $pdo->query("SELECT room_id, room_number, room_status FROM room WHERE room_number IN ('1','3','5')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query("SELECT c.room_id, r.room_number, c.ctr_status FROM contract c JOIN room r ON c.room_id = r.room_id WHERE r.room_number IN ('1','3','5')");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

$stmt3 = $pdo->query("SELECT b.room_id, r.room_number, b.bkg_status FROM booking b JOIN room r ON b.room_id = r.room_id WHERE r.room_number IN ('1','3','5')");
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
