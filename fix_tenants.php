<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->exec("UPDATE tenant SET tnt_status = '0' WHERE tnt_status = '4'");
echo "Updated " . $stmt . " tenants to 0 (Moved Out).\n";

$stmt2 = $pdo->exec("
    UPDATE booking b
    JOIN contract c ON b.tnt_id = c.tnt_id AND b.room_id = c.room_id
    SET b.bkg_status = '2'
    WHERE b.bkg_status IN ('0', '1')
");
echo "Updated " . $stmt2 . " bookings to 2 (Entered).\n";

