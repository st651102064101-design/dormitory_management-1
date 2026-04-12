<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT b.bkg_id, b.tnt_id, t.tnt_phone, b.bkg_status, tw.completed FROM booking b JOIN tenant t ON b.tnt_id = t.tnt_id LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id WHERE b.bkg_id IN (775954892, 775697436)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
