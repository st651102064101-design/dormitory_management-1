<?php
require 'ConnectDB.php';
$pdo = connectDB();
$sql = "
    SELECT
        t.tnt_id,
        b.bkg_id,
        b.bkg_status,
        b.room_id,
        c.ctr_status,
        tw.id as tw_id,
        tw.completed,
        (SELECT 1 FROM contract c3
         LEFT JOIN termination t3 ON c3.ctr_id = t3.ctr_id
         WHERE c3.room_id = b.room_id
           AND ((c3.ctr_status = '0' AND (c3.ctr_end IS NULL OR c3.ctr_end >= CURDATE()))
               OR (c3.ctr_status = '2' AND (t3.term_date IS NULL OR t3.term_date >= CURDATE())))
           AND COALESCE(c3.tnt_id, '') <> COALESCE(b.tnt_id, '')) as has_other_active_contract
    FROM booking b
    INNER JOIN tenant t ON b.tnt_id = t.tnt_id
    LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
    LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
    WHERE b.bkg_id = 775954892
";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
