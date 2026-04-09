<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

// 1. Find all expenses where it's the very first bill of the contract (either matching ctr_start month OR it's the earliest expense record) 
// and the utility record is missing, causing HUGE calculations because start meter was assumed 0.

$stmt = $pdo->query("SELECT c.ctr_id, c.ctr_start FROM contract c");
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contracts as $ctr) {
    $ctrId = $ctr['ctr_id'];
    
    // Get the earliest expense
    $expStmt = $pdo->prepare("SELECT exp_id, exp_month, exp_water, exp_elec_chg, exp_water_unit, exp_elec_unit, room_price 
                              FROM expense WHERE ctr_id = ? ORDER BY exp_month ASC LIMIT 1");
    $expStmt->execute([$ctrId]);
    $firstExp = $expStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($firstExp) {
        $expId = $firstExp['exp_id'];
        
        // If this earliest expense has a huge water/elec unit value (e.g. > 100 which is unlikely for a single month usage, especially first month)
        // Or if it simply doesn't have a checkin_record and no prior utility.
        if ($firstExp['exp_water'] > 0 || $firstExp['exp_elec_chg'] > 0) {
            $month = date('m', strtotime($firstExp['exp_month']));
            $year = date('Y', strtotime($firstExp['exp_month']));
            
            // Check if there is a utility record for this month
            $utlStmt = $pdo->prepare("SELECT utl_id FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
            $utlStmt->execute([$ctrId, $month, $year]);
            $utl = $utlStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$utl) {
                // Insert a zeroed utility record to mark it as the start, and zero out the expense!
                $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) 
                               VALUES (?, 0, 0, 0, 0, ?)")->execute([$ctrId, $firstExp['exp_month']]);
                               
                $pdo->prepare("UPDATE expense SET exp_water_unit=0, exp_elec_unit=0, exp_water=0, exp_elec_chg=0, exp_total=room_price 
                               WHERE exp_id = ?")->execute([$expId]);
                echo "Fixed ctr_id $ctrId for month {$firstExp['exp_month']}\n";
            }
        }
    }
}
echo "Done\n";
