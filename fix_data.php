<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

// Fix room 3 (ctr_id=3) - set start=0 so usage = end - start = 1-0 = 1
$pdo->exec("UPDATE utility SET utl_water_start = 0, utl_elec_start = 0 WHERE ctr_id = 3 AND MONTH(utl_date) = 12 AND YEAR(utl_date) = 2025");

echo "Fixed! Now checking:\n";
$stmt = $pdo->query("SELECT * FROM utility WHERE ctr_id = 3 ORDER BY utl_date DESC LIMIT 1");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
