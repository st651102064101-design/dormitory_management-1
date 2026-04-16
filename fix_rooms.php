<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$pdo->exec("UPDATE room SET room_status = '0'");
$pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (
    SELECT 1 FROM contract c WHERE c.room_id = room.room_id AND c.ctr_status = '0'
) OR EXISTS (
    SELECT 1 FROM booking b WHERE b.room_id = room.room_id AND b.bkg_status = '1'
)");

$stmt = $pdo->query("SELECT room_status, COUNT(*) FROM room GROUP BY room_status");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
