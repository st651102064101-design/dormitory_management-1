<?php
$code = file_get_contents('Reports/tenant_wizard.php');

$billSvg = '\'<svg class=\"bill-anim\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><rect class=\"b-doc\" x=\"5\" y=\"2\" width=\"14\" height=\"18\" rx=\"2\" stroke=\"rgba(255,255,255,0.85)\" stroke-width=\"1.8\" fill=\"rgba(255,255,255,0.1)\"/><line class=\"b-line1\" x1=\"8\" y1=\"7\" x2=\"16\" y2=\"7\" stroke=\"#fff\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-dasharray=\"8\" stroke-dashoffset=\"8\"/><line class=\"b-line2\" x1=\"8\" y1=\"11\" x2=\"16\" y2=\"11\" stroke=\"#fff\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-dasharray=\"8\" stroke-dashoffset=\"8\"/><line class=\"b-line3\" x1=\"8\" y1=\"15\" x2=\"13\" y2=\"15\" stroke=\"rgba(255,255,255,0.7)\" stroke-width=\"1.5\" stroke-linecap=\"round\" stroke-dasharray=\"8\" stroke-dashoffset=\"8\"/></svg>\'';

// 1. Change initial definition
$code = preg_replace(
    '/\$step5CircleClass = \$step5 \? \'completed\' : \(\(\$currentStep == 5\) \? \'current\' : \'pending\'\);/',
    '$step5CircleClass = $step5 ? \'current\' : (($currentStep == 5) ? \'current\' : \'pending\');',
    $code
);

$code = preg_replace(
    '/\$step5CircleLabel = \$step5 \? \'✓\' : \(\(\$currentStep == 5\) \? \'<svg class="bill-anim".*?<\/svg>\' : \'5\'\);/',
    '$step5CircleLabel = ($step5 || $currentStep == 5) ? ' . str_replace('\'', '"', $billSvg) . ' : \'5\';',
    $code
);

// 2. Change firstBillPaid && latestBillPaid block
$code = preg_replace(
    '/\$step5CircleClass = \'completed\';\s*\$step5CircleLabel = \'✓\';\s*\$step5Tooltip = \'5. ชำระแล้ว \(\' \. \$latestMonthDisplay \. \'\)\';/',
    '$step5CircleClass = \'current\';
                                        $step5CircleLabel = ' . str_replace('\'', '"', $billSvg) . ';
                                        $step5Tooltip = \'5. ชำระแล้ว (\' . $latestMonthDisplay . \')\';',
    $code
);

// 3. Change meterBillDone && firstBillPaid && latestBillPaid block
$code = preg_replace(
    '/\$step5CircleClass = \'completed\';\s*\$step5CircleLabel = \'✓\';\s*\$displayMonth = \$latestMonthDisplay !== \'-\' \? \$latestMonthDisplay : \$firstBillMonthDisplay;\s*\$step5Tooltip = \'5. ชำระแล้ว \(\' \. \$displayMonth \. \'\)\';/',
    '$step5CircleClass = \'current\';
                                        $step5CircleLabel = ' . str_replace('\'', '"', $billSvg) . ';
                                        $displayMonth = $latestMonthDisplay !== \'-\' ? $latestMonthDisplay : $firstBillMonthDisplay;
                                        $step5Tooltip = \'5. ชำระแล้ว (\' . $displayMonth . \')\';',
    $code
);

// 4. Change canOpenStep5Circle to always allow if ctr_id > 0
$code = preg_replace(
    '/\$canOpenStep5Circle = !\$isCancelPending\s*&&\s*\(int\)\$tenant\[\'ctr_id\'\] > 0\s*&&\s*\(\s*\$step5\s*\|\|\s*\$step4\s*\|\|\s*\$step5CircleClass === \'completed\'\s*\|\|\s*\$currentStep >= 5\s*\|\|\s*\(int\)\(\$tenant\[\'completed\'\] \?\? 0\) === 1\s*\);/s',
    '$canOpenStep5Circle = (int)$tenant[\'ctr_id\'] > 0;',
    $code
);

file_put_contents('Reports/tenant_wizard.php', $code);
echo "Done";
?>
