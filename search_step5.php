<?php
$content = file_get_contents('Reports/tenant_wizard.php');
$lines = explode("\n", $content);
$found = [];
foreach($lines as $index => $line) {
    if (strpos($line, '$step5CircleClass = ') !== false) {
        $start = max(0, $index - 5);
        $end = min(count($lines)-1, $index + 20);
        for($i = $start; $i <= $end; $i++) {
            $found[] = ($i+1) . ": " . $lines[$i];
        }
        $found[] = "---------";
    }
}
echo implode("\n", $found);
