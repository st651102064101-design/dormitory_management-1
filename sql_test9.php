<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
print "=== CONTRACTS FOR ROOM 1 ===\n";
print_r($pdo->query("SELECT * FROM contract WHERE room_id = (SELECT room_id FROM room WHERE room_number = '1') ORDER BY ctr_id DESC")->fetchAll(PDO::FETCH_ASSOC));

print "\n=== BOOKINGS FOR ROOM 1 ===\n";
print_r($pdo->query("SELECT * FROM booking WHERE room_id = (SELECT room_id FROM room WHERE room_number = '1') ORDER BY bkg_id DESC")->fetchAll(PDO::FETCH_ASSOC));

print "\n=== ROOM 1 STATUS ===\n";
print_r($pdo->query("SELECT * FROM room WHERE room_number = '1'")->fetchAll(PDO::FETCH_ASSOC));
