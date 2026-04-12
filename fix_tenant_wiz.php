<?php
$f = 'Reports/tenant_wizard.php';
$c = file_get_contents($f);

// Fix first_bill_paid condition
$c = preg_replace(
    '/(AND COALESCE\(\(\s*SELECT SUM\(p\.pay_amount\)\s*FROM payment p\s*WHERE p\.exp_id = e_first\.exp_id\s*AND p\.pay_status = \'1\'\s*)AND TRIM\(COALESCE\(p\.pay_remark, \'\'\)\) <> \'มัดจำ\'(\s*\), 0\) >= )\(e_first\.exp_total - COALESCE\(\(SELECT SUM\(pr\.pay_amount\) FROM payment pr WHERE pr\.exp_id = e_first\.exp_id AND pr\.pay_status = \'1\' AND TRIM\(COALESCE\(pr\.pay_remark, \'\'\)\) = \'มัดจำ\'\), 0\)\)( - 0\.00001)/s',
    '$1$2e_first.exp_total$3',
    $c
);

// Fix latest_bill_paid condition
$c = preg_replace(
    '/(AND COALESCE\(\(\s*SELECT SUM\(p\.pay_amount\)\s*FROM payment p\s*WHERE p\.exp_id = e_latest\.exp_id\s*AND p\.pay_status = \'1\'\s*)AND TRIM\(COALESCE\(p\.pay_remark, \'\'\)\) <> \'มัดจำ\'(\s*\), 0\) >= )\(e_latest\.exp_total - COALESCE\(\(SELECT SUM\(pr\.pay_amount\) FROM payment pr WHERE pr\.exp_id = e_latest\.exp_id AND pr\.pay_status = \'1\' AND TRIM\(COALESCE\(pr\.pay_remark, \'\'\)\) = \'มัดจำ\'\), 0\)\)( - 0\.00001)/s',
    '$1$2e_latest.exp_total$3',
    $c
);

file_put_contents($f, $c);
