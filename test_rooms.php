<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("
    SELECT r.room_number,
           CASE 
               WHEN EXISTS (SELECT 1 FROM contract c WHERE c.room_id = r.room_id AND c.ctr_status = '0') THEN '1' 
               WHEN EXISTS (SELECT 1 FROM booking b WHERE b.room_id = r.room_id AND b.bkg_status = '1') THEN '1'
               ELSE '0' 
           END AS room_status
    FROM room r
");
$rooms = $stmt->fetchAll();
$occupied = array_filter($rooms, fn($r) => $r['room_status'] === '1');
echo count($occupied) . " occupied rooms: " . implode(', ', array_column($occupied, 'room_number'));
