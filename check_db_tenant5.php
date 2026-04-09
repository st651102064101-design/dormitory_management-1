<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
    SELECT 
        b.bkg_id, t.tnt_name, t.tnt_phone, b.bkg_date
    FROM tenant t
    LEFT JOIN booking b ON t.tnt_id = b.tnt_id AND b.bkg_status IN ('1', '2')
    LEFT JOIN room r ON b.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
    LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
    WHERE t.tnt_phone = '0980102587'
    ORDER BY b.bkg_id DESC
");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
