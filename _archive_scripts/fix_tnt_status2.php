<?php
require 'ConnectDB.php';
$pdo = connectDB();
// tenants with NO active contract (ctr_status = 0) and NO pending contract (ctr_status = 2) should NOT be active.
// In dormitory, they either have '0' (ย้ายออก) or '4' (ยกเลิกจองห้อง).
// Let's update `tnt_status` = '4' if they are '3' (จองห้อง) but all their contracts are '1' (cancelled)
$pdo->exec("UPDATE tenant SET tnt_status = '4' WHERE tnt_status = '3' AND tnt_id IN (
    SELECT t.tnt_id FROM (SELECT * FROM tenant) t
    JOIN contract c ON t.tnt_id = c.tnt_id
    GROUP BY t.tnt_id
    HAVING MIN(c.ctr_status) = 1 AND MAX(c.ctr_status) = 1
)");

echo "Fixed tenant status\n";
?>
