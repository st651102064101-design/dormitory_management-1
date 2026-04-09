<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
    SELECT 
        b.bkg_id, b.bkg_status, b.bkg_date, t.tnt_name, t.tnt_phone,
        c.ctr_id, c.ctr_status
    FROM booking b
    JOIN tenant t ON b.tnt_id = t.tnt_id
    LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
    WHERE t.tnt_phone = '0980102587' AND b.bkg_status IN ('1','2')
    ORDER BY b.bkg_date DESC
");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
