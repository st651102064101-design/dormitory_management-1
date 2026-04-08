<?php
$f = 'Reports/settings/settings_data.php';
$data = file_get_contents($f);
$data = preg_replace("/\s*\\\$wsEnabled.*?;\s*/s", "", $data);
$data = preg_replace("/\s*\\\$wsUrl.*?;\s*/s", "", $data);
$data = preg_replace("/\s*\\\$wsPort.*?;\s*/s", "", $data);
$data = preg_replace("/\s*\\\$wsHost.*?;\s*/s", "", $data);
file_put_contents($f, $data);
echo "Patched settings_data.php\n";
