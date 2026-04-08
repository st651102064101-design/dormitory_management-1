<?php
$f = 'Reports/settings/section_system.php';
$data = file_get_contents($f);

// Remove the WebSocket Row
$data = preg_replace("/\s*<!-- WebSocket -->\s*<div class=\"apple-settings-row\" data-sheet=\"sheet-websocket\">.*?<\/div>\s*<\/div>\s*<\/div>/s", "  </div>\n</div>", $data);

// Remove the WebSocket Sheet
$data = preg_replace("/\s*<!-- Sheet: WebSocket -->\s*<div class=\"apple-sheet-overlay\" id=\"sheet-websocket\">.*?(?=<\?php include_once __DIR__ \. '\/\.\.\/includes\/apple_alert\.php'; \?>)/s", "\n\n", $data);

file_put_contents($f, $data);
echo "Patched settings UI\n";
