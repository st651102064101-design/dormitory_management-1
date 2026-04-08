<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "Total Rooms: " . $pdo->query("SELECT COUNT(*) FROM room")->fetchColumn() . "\n";
echo "Active Tenants: " . $pdo->query("SELECT COUNT(*) FROM tenant WHERE tnt_status = 1")->fetchColumn() . "\n";
echo "Pending Bookings: " . $pdo->query("SELECT COUNT(*) FROM booking WHERE bkg_status = 1")->fetchColumn() . "\n";
