<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->prepare("
    SELECT tnt_id FROM tenant_workflow 
    GROUP BY tnt_id HAVING COUNT(*) > 1
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach($tenants as $tnt_id) {
    if (empty($tnt_id)) continue;
    $stmt2 = $pdo->prepare("SELECT MAX(id) FROM tenant_workflow WHERE tnt_id = ?");
    $stmt2->execute([$tnt_id]);
    $latest = $stmt2->fetchColumn();
    
    if ($latest) {
        $undo = $pdo->prepare("
            UPDATE tenant_workflow 
            SET completed = 1
            WHERE tnt_id = ? AND id < ?
        ");
        $undo->execute([$tnt_id, $latest]);
        echo "Patched older workflows for {$tnt_id}\n";
    }
}
