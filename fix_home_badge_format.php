<?php
$files = glob("Tenant/*.php");

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    $pattern = '/(หน้าหลัก<\?php if \(\$homeBadgeCount > 0\): \?><span class="nav-badge">!<\\/span><\?php endif; \?>)\s*<\/a>\s*<a href="report_bills\.php/s';
    $replacement = "$1\n            </a>\n            <a href=\"report_bills.php";
    
    $newContent = preg_replace($pattern, $replacement, $content);

    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Reformatted $file\n";
    }
}
echo "Done.\n";
