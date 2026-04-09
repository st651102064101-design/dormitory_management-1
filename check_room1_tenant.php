<?php
require 'ConnectDB.php';
$pdo = connectDB();
$query = "SELECT b.*, rm.room_number, 
            COALESCE(t.tnt_name, 'ยังไม่มีผู้เช่า') as tnt_name,
            COALESCE(t.tnt_status, '') as tnt_status,
            t.tnt_id
            FROM booking b 
            LEFT JOIN room rm ON b.room_id = rm.room_id 
            LEFT JOIN contract c ON rm.room_id = c.room_id AND c.ctr_status IN ('0', '1')
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            WHERE rm.room_number = '1'
            ORDER BY b.bkg_date DESC";
$stmt = $pdo->query($query);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
