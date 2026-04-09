<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$ctrId = 774530857;
$pdo->exec("UPDATE utility SET utl_water_start=789, utl_water_end=789, utl_elec_start=4225, utl_elec_end=4225 WHERE ctr_id = $ctrId");
echo "Updated utility records for 774530857 to 789 and 4225\n";
