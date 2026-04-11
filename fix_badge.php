<?php
$files = [
    'Tenant/index.php',
    'Tenant/report_bills.php',
    'Tenant/repair.php',
    'Tenant/profile.php',
    'Tenant/verify_payment.php',
    'Tenant/termination.php',
    'Tenant/report_contract.php',
    'Tenant/payment.php'
];

$findSQL = <<<SQL
            AND COALESCE((
                SELECT SUM(p.pay_amount) FROM payment p
                WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
            ), 0) < e.exp_total
SQL;

$replaceSQL = <<<SQL
            AND (
                GREATEST(0, (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0)) - COALESCE((
                    SELECT SUM(p.pay_amount) FROM payment p
                    WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                    AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'
                ), 0))
                +
                GREATEST(0, (e.exp_total - (COALESCE(e.room_price, 0) + COALESCE(e.exp_elec_chg, 0) + COALESCE(e.exp_water, 0))) - COALESCE((
                    SELECT SUM(p.pay_amount) FROM payment p
                    WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
                    AND TRIM(COALESCE(p.pay_remark, '')) = 'มัดจำ'
                ), 0))
            ) > 0
SQL;

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $content = str_replace($findSQL, $replaceSQL, $content);
        file_put_contents($file, $content);
        echo "Patched \$billCount in \$file\n";
    }
}
