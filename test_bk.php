<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
echo "Active contracts (ctr_status=0): " . $pdo->query("SELECT count(DISTINCT room_id) FROM contract WHERE ctr_status = '0'")->fetchColumn() . "\n";
echo "Active bookings (bkg_status=1): " . $pdo->query("SELECT count(DISTINCT room_id) FROM booking WHERE bkg_status = '1'")->fetchColumn() . "\n";

$Rooms = $pdo->query("SELECT room_id FROM contract WHERE ctr_status = '0' UNION SELECT room_id FROM booking WHERE bkg_status = '1'")->fetchAll(PDO::FETCH_COLUMN);
echo "Occupied rooms: " . count($Rooms) . "\n";
