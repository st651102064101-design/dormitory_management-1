<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
    SELECT 
        t.tnt_id, t.tnt_name, t.tnt_phone,
        b.bkg_id, b.bkg_date, b.bkg_checkin_date, b.bkg_status,
        c.ctr_id, cc.ctr_id as cc_id
    FROM tenant t
    LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
    LEFT JOIN room r ON b.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.ctr_status = '0'
    LEFT JOIN contract cc ON t.tnt_id = cc.tnt_id AND cc.room_id = b.room_id AND cc.ctr_status = '1'
    WHERE t.tnt_phone = '0980102587' AND cc.ctr_id IS NULL
    ORDER BY b.bkg_date DESC
");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
