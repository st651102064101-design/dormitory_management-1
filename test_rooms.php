<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();
$sql = "UPDATE room SET room_status = '0'";
$pdo->exec($sql);
$sql = "UPDATE room SET room_status = '1' WHERE EXISTS (
    SELECT 1 FROM contract c WHERE c.room_id = room.room_id AND c.ctr_status = '0'
) OR EXISTS (
    SELECT 1 FROM booking b WHERE b.room_id = room.room_id AND b.bkg_status = '1'
)";
$pdo->exec($sql);
$rooms = $pdo->query("SELECT room_id, room_number, room_status FROM room ORDER BY CAST(room_number AS UNSIGNED)")->fetchAll(PDO::FETCH_ASSOC);
foreach($rooms as $r) {
    if ($r['room_status'] == '1') {
        echo "Room {$r['room_number']} is occupied.\n";
    }
}
