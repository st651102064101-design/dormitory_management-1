<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

// 1. Sync room_status directly into the database so admin pages looking at r.room_status are right.
$pdo->exec("
    UPDATE room r
    SET room_status = CASE 
        WHEN EXISTS (SELECT 1 FROM contract c WHERE c.room_id = r.room_id AND c.ctr_status = '0') THEN '1' 
        WHEN EXISTS (
            SELECT 1 FROM contract c 
            LEFT JOIN termination tm ON tm.ctr_id = c.ctr_id
            WHERE c.room_id = r.room_id AND c.ctr_status = '2' AND (tm.term_date IS NULL OR tm.term_date >= CURDATE())
        ) THEN '1'
        WHEN EXISTS (
            SELECT 1 FROM booking b 
            WHERE b.room_id = r.room_id AND b.bkg_status = '1'
            AND NOT EXISTS (
                SELECT 1 FROM contract c2 WHERE c2.room_id = b.room_id AND c2.tnt_id = b.tnt_id
            )
        ) THEN '1'
        ELSE '0' 
    END
");
echo "Room status synced.";
