<?php
$f = 'Reports/settings/apple-settings.js';
$data = file_get_contents($f);
$data = preg_replace("/\s*this\.initWebSocketSettings\(\);/", '', $data);
$data = preg_replace("/\s*\/\/\s*=====\s*Form Handling\s*=====\s*initWebSocketSettings\(\).*?initThemeSelector\(\) \{/s", "\n  // ===== Form Handling =====\n\n  initThemeSelector() {", $data);
file_put_contents($f, $data);
echo "Patched JS\n";
