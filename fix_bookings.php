<?php
require 'ConnectDB.php';
$pdo = connectDB();

// 1. Booking ที่ไม่มี contract หรือ contract ถูกยกเลิกทั้งหมด ให้สถานะเป็นยกเลิก (0)
$pdo->exec("UPDATE booking SET bkg_status = '0' WHERE bkg_status IN ('1', '2', '3') AND tnt_id IN (
    SELECT tnt_id FROM (
        SELECT tnt_id FROM tenant t WHERE t.tnt_status IN ('0', '4')
    ) AS sub
)");

// 2. สำหรับนาย เกรียงไกร คงเมือง (177569542458) มี 2 bookings
// อันล่าสุดคือ 775697436, ส่วนอันเก่า 775695448 ควรเป็นยกเลิก (0)
$pdo->exec("UPDATE booking SET bkg_status = '0' WHERE bkg_id = 775695448");

// 3. 775619508 (เกรียงไกร คงเมืองง) -> tnt_status = 3, แต่สัญญาโดนยกเลิก (ctr_status = 1)
// ควรให้ bkg เป็น 0
$pdo->exec("UPDATE booking SET bkg_status = '0' WHERE tnt_id = '1775619508'");

// 4. Update status 3 to 0 since 3 doesn't exist in standard
$pdo->exec("UPDATE booking SET bkg_status = '0' WHERE bkg_status = '3'");

echo "Fixed bookings\n";
?>
