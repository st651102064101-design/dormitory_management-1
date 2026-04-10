<?php
$files = glob("Tenant/*.php");

$replacement = <<<'EOD'
    $homeBadgeCount = 0;
    try {
        $homeBadgeStmt = $pdo->prepare("
            SELECT 1 
            FROM contract c
            LEFT JOIN signature_logs sl ON c.ctr_id = sl.contract_id AND sl.signer_type = 'tenant'
            LEFT JOIN tenant_workflow tw ON c.tnt_id = tw.tnt_id
            WHERE c.ctr_id = ? AND c.ctr_status != '1' AND tw.current_step >= 3 AND sl.id IS NULL
            LIMIT 1
        ");
        $homeBadgeStmt->execute([$contract['ctr_id'] ?? 0]);
        if ($homeBadgeStmt->fetchColumn()) {
            $homeBadgeCount = 1;
        }
    } catch (Exception $e) { error_log("Exception calculating home badge count in " . __FILE__ . ": " . $e->getMessage()); }

    $billCount = 0;
EOD;

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    // Add the $homeBadgeCount query right before $billCount = 0;
    $pattern = '/\s*\$billCount\s*=\s*0;/s';
    
    // Check if the file contains the bottom nav
    if (strpos($content, '<nav class="bottom-nav">') === false) continue;
    
    // Apply replacement only once so we replace the right one
    $newContent = preg_replace($pattern, "\n" . $replacement, $content, 1);
    
    // Now replace the "หน้าหลัก" HTML to show the badge
    $navReplacement = <<<'EOD'
                หน้าหลัก<?php if ($homeBadgeCount > 0): ?><span class="nav-badge">!</span><?php endif; ?>
            </a>
EOD;
    $navPattern = '/หน้าหลัก\s*<\/a>\s*/s';
    $newContent = preg_replace($navPattern, $navReplacement . "            ", $newContent);

    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Updated $file\n";
    }
}
echo "Done.\n";
