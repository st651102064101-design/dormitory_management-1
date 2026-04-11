<?php
$file = 'Tenant/index.php';
$content = file_get_contents($file);

$findSQL = <<<SQL
        LEFT JOIN (
            SELECT exp_id, COALESCE(SUM(pay_amount), 0) AS submitted_amount
            FROM payment
            WHERE pay_status IN ('0', '1')
              AND TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ'
            GROUP BY exp_id
        ) ps ON ps.exp_id = e.exp_id
        WHERE (e.exp_total - COALESCE(ps.submitted_amount, 0)) > 0
SQL;

$replaceSQL = <<<SQL
        LEFT JOIN (
            SELECT 
                exp_id, 
                COALESCE(SUM(CASE WHEN TRIM(COALESCE(pay_remark, '')) <> 'มัดจำ' THEN pay_amount ELSE 0 END), 0) AS submitted_rent_amount,
                COALESCE(SUM(CASE WHEN TRIM(COALESCE(pay_remark, '')) = 'มัดจำ' THEN pay_amount ELSE 0 END), 0) AS submitted_deposit_amount
            FROM payment
            WHERE pay_status IN ('0', '1')
            GROUP BY exp_id
        ) ps ON ps.exp_id = e.exp_id
        WHERE (
            GREATEST(0, (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0)) - COALESCE(ps.submitted_rent_amount, 0))
            +
            GREATEST(0, (e.exp_total - (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0))) - COALESCE(ps.submitted_deposit_amount, 0))
        ) > 0
SQL;

$content = str_replace($findSQL, $replaceSQL, $content);
file_put_contents($file, $content);
echo "Patched \$unpaidStmt in index.php\n";
