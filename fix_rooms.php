<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();
$sql = "UPDATE room SET room_status = '0'";
$pdo->exec($sql);
$sql = "UPDATE room SET room_status = '1' WHERE EXISTS (
    SELECT 1 FROM contract c 
    WHERE c.room_id = room.room_id AND c.ctr_status = '0'
)";
$pdo->exec($sql);
echo "Updated.\n";
