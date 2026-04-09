<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->query("
    SELECT e.exp_id, e.ctr_id, e.exp_month, 
           COALESCE(c.water_meter_start, 0) as water_meter_start,
           COALESCE(c.elec_meter_start, 0) as elec_meter_start
    FROM expense e
    LEFT JOIN utility u ON e.ctr_id = u.ctr_id AND MONTH(e.exp_month) = MONTH(u.utl_date) AND YEAR(e.exp_month) = YEAR(u.utl_date)
    LEFT JOIN checkin_record c ON e.ctr_id = c.ctr_id
    WHERE u.utl_id IS NULL
");

while ($row = $stmt->fetch()) {
    $water = (int)$row['water_meter_start'];
    $elec = (int)$row['elec_meter_start'];
    $ctr = $row['ctr_id'];
    $date = $row['exp_month'];
    
    // Check if there are ANY utility records at all for this contract. If so, taking the earliest one's start could help.
    $checkUtl = $pdo->query("SELECT utl_water_start, utl_elec_start FROM utility WHERE ctr_id = $ctr ORDER BY utl_date ASC LIMIT 1")->fetch();
    if ($checkUtl && $water == 0) {
        $water = (int)$checkUtl['utl_water_start'];
        $elec = (int)$checkUtl['utl_elec_start'];
    }
    
    $pdo->exec("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) 
                VALUES ($ctr, $water, $water, $elec, $elec, '$date')");
                
    // In case the expense is mistakenly having utility costs logic we zero them.
    $pdo->exec("UPDATE expense SET exp_water_unit=0, exp_elec_unit=0, exp_water=0, exp_elec_chg=0, exp_total=room_price 
                WHERE exp_id = {$row['exp_id']} AND (exp_water > 0 OR exp_elec_chg > 0)");
                
    echo "Fixed contract $ctr month $date with start $water, $elec\n";
}
echo "Done.\n";
