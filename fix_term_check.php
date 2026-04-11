<?php
$files = [
    'Tenant/index.php',
    'Tenant/report_contract.php',
    'Tenant/termination.php'
];

$findSQL = <<<SQL
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND e.exp_total > COALESCE((
                    SELECT SUM(p.pay_amount) 
                    FROM payment p 
                    WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                ), 0)
           ) AS unpaid_bills_count,
SQL;

$replaceSQL = <<<SQL
           (
              SELECT COUNT(*)
              FROM expense e
              WHERE e.ctr_id = c.ctr_id
                AND (
                    GREATEST(0, (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0)) - COALESCE((
                        SELECT SUM(p.pay_amount) FROM payment p
                        WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                        AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                    ), 0))
                    +
                    GREATEST(0, (e.exp_total - (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0))) - COALESCE((
                        SELECT SUM(p.pay_amount) FROM payment p
                        WHERE p.exp_id = e.exp_id AND p.pay_status = '1'
                        AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ'
                    ), 0))
                ) > 0
           ) AS unpaid_bills_count,
SQL;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $content = str_replace($findSQL, $replaceSQL, $content);
        file_put_contents($file, $content);
        echo "Patched \$termCheckStmt in \$file\n";
    }
}
