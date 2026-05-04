<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require 'ConnectDB.php';
$pdo = connectDB();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Try each tab query
try {
    $expenseStmt = $pdo->query("
             SELECT e.exp_id, e.exp_month, e.exp_total,
                 e.room_price, e.exp_elec_chg, e.exp_water,
                   c.ctr_id, r.room_number, COALESCE(t.tnt_name, '') AS tnt_name,
                                     CASE WHEN EXISTS (
                                         SELECT 1
                                         FROM utility u2
                                         WHERE u2.ctr_id = e.ctr_id
                                             AND YEAR(u2.utl_date) = YEAR(e.exp_month)
                                             AND MONTH(u2.utl_date) = MONTH(e.exp_month)
                                             AND u2.utl_water_end IS NOT NULL
                                             AND u2.utl_elec_end IS NOT NULL
                                     ) THEN 1 ELSE 0 END AS has_complete_meter,
                   COALESCE(pay_agg.approved_amount, 0) AS paid_amount,
                   COALESCE(pay_agg.pending_count, 0) AS pending_count
            FROM expense e
            INNER JOIN (
                SELECT ctr_id, DATE_FORMAT(exp_month, '%Y-%m') AS month_key, MAX(exp_id) AS latest_exp_id
                FROM expense
                GROUP BY ctr_id, DATE_FORMAT(exp_month, '%Y-%m')
            ) latest_exp ON latest_exp.ctr_id = e.ctr_id
                          AND DATE_FORMAT(e.exp_month, '%Y-%m') = latest_exp.month_key
                          AND e.exp_id = latest_exp.latest_exp_id
            INNER JOIN contract c ON e.ctr_id = c.ctr_id AND c.ctr_status = '0'
            LEFT JOIN room r ON c.room_id = r.room_id
            LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
            LEFT JOIN tenant_workflow tw ON tw.ctr_id = c.ctr_id
            LEFT JOIN (
                SELECT exp_id,
                                        SUM(CASE WHEN pay_status = '1' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN pay_amount ELSE 0 END) AS approved_amount,
                                        SUM(CASE WHEN pay_status = '0' AND pay_proof IS NOT NULL AND pay_proof <> '' AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN 1 ELSE 0 END) AS pending_count
                FROM payment GROUP BY exp_id
            ) pay_agg ON pay_agg.exp_id = e.exp_id
            WHERE (COALESCE(tw.step_5_confirmed, 0) = 1 OR COALESCE(tw.current_step, 0) >= 5)
              AND YEAR(e.exp_month) = YEAR(CURDATE())
              AND MONTH(e.exp_month) = MONTH(CURDATE())
              AND (
                COALESCE(pay_agg.pending_count, 0) > 0
                OR COALESCE(pay_agg.approved_amount, 0) < COALESCE(e.exp_total, 0)
              )
            ORDER BY r.room_number ASC
        ");
} catch(Exception $e) { echo "EXPENSE FAILED: " . $e->getMessage() . "\n"; }

try {
        $paymentStmt = $pdo->query("
            SELECT x.payment_kind, x.pay_id, x.pay_amount, x.pay_date, x.pay_status, x.pay_proof, x.pay_remark,
                   x.exp_id, x.exp_month, x.exp_total, x.room_number, x.tnt_name
            FROM (
                SELECT 'pending' AS payment_kind,
                       p.pay_id, p.pay_amount, p.pay_date, p.pay_status, p.pay_proof, p.pay_remark,
                       e.exp_id, e.exp_month, e.exp_total,
                       r.room_number, COALESCE(t.tnt_name, '') AS tnt_name
                FROM payment p
                LEFT JOIN expense e ON p.exp_id = e.exp_id
                LEFT JOIN contract c ON e.ctr_id = c.ctr_id
                LEFT JOIN room r ON c.room_id = r.room_id
                LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
                WHERE p.pay_status = '0'
                  AND p.pay_proof IS NOT NULL AND p.pay_proof <> ''

                UNION ALL

                SELECT 'unpaid' AS payment_kind,
                       NULL AS pay_id,
                       NULL AS pay_amount,
                       NULL AS pay_date,
                       '0' AS pay_status,
                       NULL AS pay_proof,
                       'รอชำระ' AS pay_remark,
                       e.exp_id, e.exp_month, e.exp_total,
                       r.room_number, COALESCE(t.tnt_name, '') AS tnt_name
                FROM expense e
                INNER JOIN contract c ON e.ctr_id = c.ctr_id
                LEFT JOIN room r ON c.room_id = r.room_id
                LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
                LEFT JOIN payment p ON p.exp_id = e.exp_id
                WHERE c.ctr_status = '0'
                  AND p.exp_id IS NULL
                  AND DATE_FORMAT(e.exp_month, '%Y-%m') > DATE_FORMAT(c.ctr_start, '%Y-%m')
                  AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
            ) x
            ORDER BY x.pay_date DESC, x.exp_month DESC
            LIMIT 50
        ");
} catch(Exception $e) { echo "PAYMENT FAILED: " . $e->getMessage() . "\n"; }

