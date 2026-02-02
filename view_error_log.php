<?php
// Read error log
$errorLog = '/Applications/XAMPP/xamppfiles/logs/php_error.log';
$lines = [];

if (file_exists($errorLog)) {
    $content = file_get_contents($errorLog);
    $allLines = explode("\n", $content);
    $lines = array_slice($allLines, -50);  // Last 50 lines
}

echo "<h2>Latest PHP Errors (Last 50 lines):</h2>\n";
echo "<pre>\n";
foreach (array_reverse($lines) as $line) {
    if (!empty(trim($line))) {
        echo htmlspecialchars($line) . "\n";
    }
}
echo "</pre>\n";

// Also check callback debug log
$callbackDebug = '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/google_callback_debug.log';
if (file_exists($callbackDebug)) {
    echo "<h2>Callback Debug Log:</h2>\n";
    echo "<pre>\n";
    echo htmlspecialchars(file_get_contents($callbackDebug));
    echo "</pre>\n";
}
?>
