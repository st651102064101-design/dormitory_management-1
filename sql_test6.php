<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("
    SELECT
        b.bkg_id, b.bkg_date,
        t.tnt_name,
        r.room_number,
        COALESCE(tw.current_step, 0) AS current_step,
        COALESCE(tw.completed, 0) AS completed,
        b.bkg_status
    FROM booking b
    INNER JOIN tenant t ON b.tnt_id = t.tnt_id
    LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
    LEFT JOIN room r ON b.room_id = r.room_id
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r(array_filter($res, function($row){ return $row['current_step'] == 5 || $row['bkg_status'] == 5; }));
