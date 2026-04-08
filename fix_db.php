<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

$tnt_id = '1775444378';

// Get the latest workflow
$stmt = $pdo->prepare("SELECT MAX(id) as latest_wf FROM tenant_workflow WHERE tnt_id = ?");
$stmt->execute([$tnt_id]);
$latest_id = $stmt->fetchColumn();

if ($latest_id) {
    // Reset any workflow that is NOT the latest for this tenant, which were corrupted
    // by the UPDATE tenant_workflow SET ... WHERE tnt_id = ? bug.
    $stmt = $pdo->prepare("
        UPDATE tenant_workflow 
        SET ctr_id = (SELECT MIN(ctr_id) FROM contract WHERE bkg_id = tenant_workflow.bkg_id OR (tnt_id = tenant_workflow.tnt_id AND ctr_status = '1'))
        WHERE tnt_id = ? AND id < ?
    ");
    $stmt->execute([$tnt_id, $latest_id]);
    echo "Fixed corrupted workflows for {$tnt_id}!\n";
}
