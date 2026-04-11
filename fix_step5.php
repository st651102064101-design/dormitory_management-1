<?php
$files = ['Tenant/index.php', 'Tenant/termination.php', 'Tenant/report_contract.php'];
foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $oldQuery = "SELECT step_5_confirmed
              FROM tenant_workflow
              WHERE tnt_id = c.tnt_id
              ORDER BY id DESC LIMIT 1";
    $newQuery = "SELECT CASE WHEN COALESCE(step_5_confirmed, 0) = 1 OR COALESCE(current_step, 0) >= 5 THEN 1 ELSE 0 END
              FROM tenant_workflow
              WHERE tnt_id = c.tnt_id
              ORDER BY id DESC LIMIT 1";
    if (strpos($content, $oldQuery) !== false) {
        $content = str_replace($oldQuery, $newQuery, $content);
        file_put_contents($file, $content);
        echo "Patched $file\n";
    } else {
        echo "Could not find target in $file\n";
    }
}
