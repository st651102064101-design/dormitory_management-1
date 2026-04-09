<?php
$files = glob("Tenant/*.php");

$replacement = <<<'EOD'
    $billCount = 0;
    try {
        $billStmt = $pdo->prepare("
            SELECT COUNT(*) FROM expense e
            INNER JOIN (
                SELECT MAX(exp_id) AS exp_id FROM expense WHERE ctr_id = ? GROUP BY exp_month
            ) latest ON e.exp_id = latest.exp_id
            WHERE e.ctr_id = ?
            AND DATE_FORMAT(e.exp_month, '%Y-%m') >= DATE_FORMAT(?, '%Y-%m')
            AND DATE_FORMAT(e.exp_month, '%Y-%m') <= DATE_FORMAT(CURDATE(), '%Y-%m')
            AND (
                e.exp_month = (SELECT MIN(e2.exp_month) FROM expense e2 WHERE e2.ctr_id = e.ctr_id)
                OR EXISTS (
                    SELECT 1
                    FROM utility u
                    WHERE u.ctr_id = e.ctr_id
                        AND YEAR(u.utl_date) = YEAR(e.exp_month)
                        AND MONTH(u.utl_date) = MONTH(e.exp_month)
                        AND u.utl_water_end IS NOT NULL
                        AND u.utl_elec_end IS NOT NULL
                )
            )
            AND COALESCE((
                SELECT SUM(p.pay_amount) FROM payment p
                WHERE p.exp_id = e.exp_id AND p.pay_status IN ('0','1')
            ), 0) < e.exp_total
        ");
        $billStmt->execute([$contract['ctr_id'], $contract['ctr_id'], $contract['ctr_start'] ?? date('Y-m-d')]);
        $billCount = (int)($billStmt->fetchColumn() ?? 0);
    } catch (Exception $e) { error_log("Exception calculating bill count in " . __FILE__ . ": " . $e->getMessage()); }
EOD;

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    // Use regex to locate the block assigning to $billCount then immediately doing try catch for $billStmt
    $pattern = '/\$billCount\s*=\s*0;\s*try\s*\{\s*\$billStmt\s*=\s*\$pdo->prepare\(.*?\);\s*\$billCount\s*=\s*\(int\)\(\$billStmt->fetchColumn\(\)\s*\?\?\s*0\);\s*\}\s*catch\s*\(.*?\)\s*\{\s*error_log\(.*?\);\s*\}/ms';
    
    $newContent = preg_replace($pattern, $replacement, $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Updated $file\n";
    } else {
        echo "Skipped $file (No match or already updated)\n";
    }
}
echo "Done.\n";
