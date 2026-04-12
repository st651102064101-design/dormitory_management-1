<?php
require 'ConnectDB.php';
$pdo = connectDB();
require 'includes/wizard_helper.php';
$items = getWizardItems($pdo)['items'];
foreach ($items as $item) {
    if ($item['current_step'] == 5) {
        $ctr = $item['ctr_id'];
        $stmt = $pdo->prepare("
        SELECT 
           (c.ctr_status = '0') as is_active,
           cr.checkin_date,
           (EXISTS (
            SELECT 1 FROM expense e_first
            WHERE e_first.ctr_id = c.ctr_id
                AND e_first.exp_month = (SELECT MIN(exp_month) FROM expense WHERE ctr_id = c.ctr_id)
                AND e_first.exp_total > 0
                AND COALESCE((SELECT SUM(pay_amount) FROM payment p WHERE p.exp_id = e_first.exp_id AND p.pay_status = '1'), 0) >= e_first.exp_total - 0.00001
           )) as is_first_paid
        FROM contract c
        LEFT JOIN checkin_record cr ON c.ctr_id = cr.ctr_id
        WHERE c.ctr_id = ?
        ");
        $stmt->execute([$ctr]);
        echo "Room {$item['room_number']}: \n";
        print_r($stmt->fetch(PDO::FETCH_ASSOC));
    }
}
