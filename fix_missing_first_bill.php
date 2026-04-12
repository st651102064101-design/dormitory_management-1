<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "Running missing first bill check...\n";

// Find tenants who have completed step 4 (meter reading) but have no regular expense
$stmt = $pdo->query("
    SELECT c.ctr_id, c.ctr_start, rt.type_price as room_price, c.tnt_id
    FROM contract c
    INNER JOIN room r ON c.room_id = r.room_id
    INNER JOIN roomtype rt ON r.type_id = rt.type_id
    WHERE c.ctr_status != '1'
");

while ($row = $stmt->fetch()) {
    $ctr_id = $row['ctr_id'];
    $room_price = $row['room_price'];
    
    $wStmt = $pdo->prepare("SELECT step_4_confirmed FROM tenant_workflow WHERE ctr_id = ? ORDER BY id DESC LIMIT 1");
    $wStmt->execute([$ctr_id]);
    $workflow = $wStmt->fetch();
    
    if ($workflow && !empty($workflow['step_4_confirmed'])) {
        $firstBillMonth = date('Y-m-01', strtotime($row['ctr_start']));
        
        $checkStmt = $pdo->prepare("
            SELECT e.exp_id 
            FROM expense e
            WHERE e.ctr_id = ? 
              AND DATE_FORMAT(e.exp_month, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')
              AND NOT EXISTS (
                  SELECT 1 FROM payment p WHERE p.exp_id = e.exp_id AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ'
              )
            LIMIT 1
        ");
        $checkStmt->execute([$ctr_id, $firstBillMonth]);
        if (!$checkStmt->fetchColumn()) {
            echo "Missing rent bill for Contract $ctr_id in $firstBillMonth. Creating...\n";
            $expenseId = (int)substr((string)time(), -9) + random_int(1, 9999);
            $ins = $pdo->prepare("
                INSERT INTO expense (exp_id, exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, room_price, exp_elec_chg, exp_water, exp_total, exp_status, ctr_id)
                VALUES (?, ?, 0, 0, 8, 18, ?, 0, 0, ?, '2', ?)
            ");
            $ins->execute([$expenseId, $firstBillMonth, $room_price, $room_price, $ctr_id]);
            echo "  -> Created exp_id = $expenseId!\n";
        }
    }
}
echo "Done.\n";
?>
