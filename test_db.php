<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT r.room_number, r.room_id, c.ctr_status, b.bkg_status FROM room r LEFT JOIN contract c ON r.room_id = c.room_id LEFT JOIN booking b ON r.room_id = b.room_id WHERE r.room_number IN ('2','4','7','8','10','11','12','13','14','18','20','23','25')");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
