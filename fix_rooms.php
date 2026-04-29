<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$pdo->exec("UPDATE room SET room_status = '0'");
$pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (
    SELECT 1 FROM contract c
    LEFT JOIN termination t ON t.ctr_id = c.ctr_id
    WHERE c.room_id = room.room_id
      AND (
        c.ctr_status = '0'
        OR (c.ctr_status = '2' AND (t.term_date IS NULL OR t.term_date >= CURDATE()))
      )
) OR EXISTS (
    SELECT 1 FROM booking b
    WHERE b.room_id = room.room_id AND b.bkg_status = '1'
      AND NOT EXISTS (
        SELECT 1 FROM contract c2
        WHERE c2.room_id = b.room_id AND c2.tnt_id = b.tnt_id
      )
)");

$stmt = $pdo->query("SELECT room_status, COUNT(*) FROM room GROUP BY room_status");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
