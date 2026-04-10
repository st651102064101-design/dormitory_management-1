<?php
require_once "ConnectDB.php";
$conn = connectDB();
// require the tenant wizard string building logic exactly!
$completedFilter = 0;
$bookingFilterCondition = "";

// Just copy everything and dump $wizardTenants
$content = file_get_contents('Reports/tenant_wizard.php');
$startPos = strpos($content, '$firstBillPaidCondition =');
$endPos = strpos($content, '$deduped = [];');

$phpCode = substr($content, $startPos, $endPos - $startPos);
file_put_contents('test_eval.php', "<?php\nrequire_once 'ConnectDB.php';\n\$conn = connectDB();\n\$completedFilter = 0;\n\$bookingFilterCondition = '';\n" . $phpCode . "\nprint_r(\$wizardTenants);\n");
