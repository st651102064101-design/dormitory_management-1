<?php
$f = 'Manage/save_system_settings.php';
$data = file_get_contents($f);
$data = preg_replace("/\s*if\s*\(\s*isset\(\s*\$_POST\['ws_enabled'\]\s*\)\s*\)\s*\{.*?\}(?=\s*header\('Content-Type: application\/json'\);(?!\s*echo json_encode\(\['success' => false, 'error' => 'รูปแบบ URL))/s", '', $data);
file_put_contents($f, $data);
echo "Patched settings PHP\n";
