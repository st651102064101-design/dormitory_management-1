<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
// Fix contract 774530857
$ctrId = 774530857;
// Insert missing utility record as history
$pdo->exec("DELETE FROM utility WHERE ctr_id = 774530857");
$pdo->exec("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) 
            VALUES ($ctrId, 0, 0, 0, 0, '2026-03-01')");
$pdo->exec("UPDATE expense SET exp_water_unit=0, exp_elec_unit=0, exp_water=0, exp_elec_chg=0, exp_total=room_price 
            WHERE ctr_id = $ctrId AND exp_month = '2026-03-01'");
echo "DB Fixed\n";
