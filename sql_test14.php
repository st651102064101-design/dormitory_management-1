<?php
require 'ConnectDB.php';
$pdo = connectDB();

$sql = "
    SELECT b.bkg_id, r.room_number,
        (c.ctr_status = '0') as is_active,
        (cr.checkin_date IS NOT NULL AND cr.checkin_date <> '0000-00-00') as has_checkin,
        (EXISTS (
            SELECT 1 FROM expense e_first
            WHERE e_first.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                AND (c.ctr_start IS NULL OR DATE_FORMAT(e_first.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m'))
                AND e_first.exp_month = (
                    SELECT MIN(e_min.exp_month) FROM expense e_min
                    WHERE e_min.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                        AND (c.ctr_start IS NULL OR DATE_FORMAT(e_min.exp_month, '%Y-%m') >= DATE_FORMAT(c.ctr_start, '%Y-%m'))
                )
                AND e_first.exp_total > 0
                AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p 
                              WHERE p.exp_id = e_first.exp_id AND p.pay_status = '1'), 0) >= e_first.exp_total - 0.00001
        )) as first_bill_paid,
        (EXISTS (
            SELECT 1 FROM utility u_meter
            WHERE u_meter.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
              AND u_meter.utl_water_end IS NOT NULL
        )) as meter_recorded,
        (EXISTS (
            SELECT 1 FROM expense e_latest
            WHERE e_latest.ctr_id = COALESCE(c.ctr_id, tw.ctr_id)
                AND e_latest.exp_month = (SELECT MAX(e_max.exp_month) FROM expense e_max WHERE e_max.ctr_id = COALESCE(c.ctr_id, tw.ctr_id))
                AND e_latest.exp_total > 0
                AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p 
                              WHERE p.exp_id = e_latest.exp_id AND p.pay_status = '1'), 0) >= e_latest.exp_total - 0.00001
        )) as latest_bill_paid
    FROM booking b
    LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
    INNER JOIN room r ON b.room_id = r.room_id
    LEFT JOIN contract c ON tw.ctr_id = c.ctr_id
    LEFT JOIN checkin_record cr ON c.ctr_id = cr.ctr_id
    WHERE r.room_number IN ('7', '9', '11') AND tw.current_step = 5
";
$stmt = $pdo->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
