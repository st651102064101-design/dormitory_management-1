<?php
require 'ConnectDB.php';
$pdo = connectDB();
$active_rooms = [2, 4, 7, 8, 10, 11, 12, 13, 14, 18, 20, 23, 25];

$sql = "SELECT r.room_id, r.room_number,
       (SELECT COUNT(*) FROM contract c WHERE c.room_id = r.room_id AND c.ctr_status = 0) as contracts,
       (SELECT COUNT(*) FROM booking b WHERE b.room_id = r.room_id AND b.bkg_status = '1') as bookings,
       (SELECT COUNT(*) FROM booking b WHERE b.room_id = r.room_id AND b.bkg_status = '2') as confirming
FROM room r ORDER BY CAST(r.room_number AS UNSIGNED) ASC;";
$stmt = $pdo->query($sql);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rooms as $row) {
    if (!$row) continue;
    $occupied = ($row['contracts'] > 0 || $row['bookings'] > 0 || $row['confirming'] > 0);
    $shouldBe = in_array((int)$row['room_number'], $active_rooms);
    
    if ($occupied && !$shouldBe) {
        echo "Room {$row['room_number']} (ID {$row['room_id']}) is OCCUPIED but should be VACANT.\n";
        // Force vacant
        $pdo->exec("UPDATE contract SET ctr_status = 1 WHERE room_id = {$row['room_id']} AND ctr_status = 0");
        $pdo->exec("UPDATE booking SET bkg_status = '3' WHERE room_id = {$row['room_id']} AND bkg_status IN ('1','2')");
    }
}
$pdo->exec("UPDATE room SET room_status = '0'"); // set all to vacant
// set active to occupied
foreach($active_rooms as $r) {
    $pdo->exec("UPDATE room SET room_status = '1' WHERE room_number = '{$r}'");
}
echo "Done fixing rooms.\n";
