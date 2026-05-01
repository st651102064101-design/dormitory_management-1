<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT utl_id, ctr_id, utl_date, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end FROM utility ORDER BY utl_id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
