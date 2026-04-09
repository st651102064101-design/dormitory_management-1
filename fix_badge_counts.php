<?php
$files = glob("Tenant/*.php");

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $content = file_get_contents($file);
    
    // Look for the billStmt prepare block
    $pattern = '/(\$billStmt = \$pdo->prepare\(")\s*(SELECT COUNT\(\*\) FROM expense e\s*INNER JOIN \(\s*SELECT MAX\(exp_id\) AS exp_id FROM expense WHERE ctr_id = \? GROUP BY exp_month\s*\) latest ON e.exp_id = latest.exp_id\s*WHERE e.ctr_id = \?\s*AND DATE_FORMAT\(e.exp_month, \'%Y-%m\'\) >= DATE_FORMAT\(\?, \'%Y-%m\'\)\s*AND DATE_FORMAT\(e.exp_month, \'%Y-%m\'\) <= DATE_FORMAT\(CURDATE\(\), \'%Y-%m\'\))(\s*AND COALESCE)/ms';
    
    $replacement = "$1\n            $2\n            AND (\n                e.exp_month = (SELECT MIN(e2.exp_month) FROM expense e2 WHERE e2.ctr_id = e.ctr_id)\n                OR EXISTS (\n                    SELECT 1\n                    FROM utility u\n                    WHERE u.ctr_id = e.ctr_id\n                        AND YEAR(u.utl_date) = YEAR(e.exp_month)\n                        AND MONTH(u.utl_date) = MONTH(e.exp_month)\n                        AND u.utl_water_end IS NOT NULL\n                        AND u.utl_elec_end IS NOT NULL\n                )\n            )$3";
    
    $newContent = preg_replace($pattern, $replacement, $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Updated $file\n";
    }
}
echo "Done.\n";
