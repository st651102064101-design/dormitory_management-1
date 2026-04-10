<?php
$files = glob("Tenant/*.php");

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    $newContent = str_replace('<span class="nav-badge">!</span>', '<span class="nav-badge">1</span>', $content);

    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
    }
}
echo "Done.\n";
