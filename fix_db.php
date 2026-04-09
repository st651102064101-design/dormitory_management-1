<?php
require 'ConnectDB.php';
$pdo = connectDB();
// อัปเดตสถานะการจองของผู้ที่เข้าพักแล้ว
$pdo->exec("UPDATE booking b JOIN tenant t ON b.tnt_id = t.tnt_id SET b.bkg_status = '2' WHERE t.tnt_status = '1' AND b.bkg_status = '1'");
// อัปเดตผู้เช่าที่สัญญายกเลิกแต่สถานะยังค้าง (เช่น จองห้อง)
// $pdo->exec("UPDATE booking SET bkg_status = '0' WHERE tnt_id IN (SELECT tnt_id FROM contract WHERE ctr_status = '1') AND bkg_status = '1'");
echo "DB Fixed\n";
?>
