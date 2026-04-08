<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT b.bkg_id, b.room_id, r.room_number, b.tnt_id, t.tnt_name, b.bkg_status, c.ctr_id, c.ctr_status FROM booking b LEFT JOIN tenant t ON b.tnt_id = t.tnt_id LEFT JOIN room r ON b.room_id = r.room_id LEFT JOIN contract c ON c.tnt_id = b.tnt_id WHERE r.room_number IN ('23', '18', '20', '7', '14', '11', '4', '8', '2') AND b.bkg_status = 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
