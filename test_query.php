<?php
require 'ConnectDB.php';
$pdo = connectDB();
$bookingStmt = $pdo->query("
    SELECT b.bkg_id, b.bkg_date, b.bkg_status,
           COALESCE(t.tnt_name, '') AS tnt_name, t.tnt_phone,
           r.room_number, rt.type_name AS roomtype_name
    FROM booking b
    LEFT JOIN tenant t ON b.tnt_id = t.tnt_id
    LEFT JOIN room r ON b.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    LEFT JOIN contract active_ctr
        ON active_ctr.room_id = b.room_id
       AND active_ctr.ctr_status IN ('0','2')
    LEFT JOIN (
        SELECT bkg_id, MAX(current_step) as max_step 
        FROM tenant_workflow 
        GROUP BY bkg_id
    ) w ON w.bkg_id = b.bkg_id
    WHERE b.bkg_status = 1
      AND active_ctr.ctr_id IS NULL
      AND (w.max_step IS NULL OR w.max_step < 3)
    ORDER BY b.bkg_date DESC
");
print_r($bookingStmt->fetchAll(PDO::FETCH_ASSOC));
