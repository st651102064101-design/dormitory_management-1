<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "🧪 Checking Room 1 Data Issue\n";
echo "=============================\n\n";

// Find contract for room 1
$stmt = $pdo->query("SELECT c.ctr_id, c.tnt_id, c.ctr_status FROM contract c WHERE c.room_id = 1 ORDER BY c.ctr_id DESC LIMIT 1");
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    echo "❌ No contract found for room 1\n";
    exit;
}

echo "📋 Contract:\n";
echo "   ID: {$contract['ctr_id']}, Tenant: {$contract['tnt_id']}, Status: {$contract['ctr_status']}\n\n";

// Check workflow step
$wfStmt = $pdo->prepare("SELECT current_step FROM tenant_workflow WHERE ctr_id = :ctr_id");
$wfStmt->execute([':ctr_id' => $contract['ctr_id']]);
$workflow = $wfStmt->fetch(PDO::FETCH_ASSOC);
$step = $workflow ? $workflow['current_step'] : 0;
echo "📊 Workflow: Step $step/5\n\n";

// Check checkin
$checkinStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM checkin_record WHERE ctr_id = :ctr_id");
$checkinStmt->execute([':ctr_id' => $contract['ctr_id']]);
$checkin = $checkinStmt->fetch(PDO::FETCH_ASSOC);
echo "✅ Checkin Records: " . $checkin['cnt'] . "\n\n";

// Check utility
$utilStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM utility WHERE ctr_id = :ctr_id");
$utilStmt->execute([':ctr_id' => $contract['ctr_id']]);
$util = $utilStmt->fetch(PDO::FETCH_ASSOC);
echo "📊 Utility Records: " . $util['cnt'] . "\n";

if ($util['cnt'] > 0) {
    $utlList = $pdo->prepare("SELECT utl_id, utl_date FROM utility WHERE ctr_id = :ctr_id ORDER BY utl_date DESC");
    $utlList->execute([':ctr_id' => $contract['ctr_id']]);
    while ($u = $utlList->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$u['utl_date']}\n";
    }
}

echo "\n🎯 Status:\n";
if ($step < 4 && $util['cnt'] > 0) {
    echo "❌ ISSUE: Utility records exist but room not checked-in (Step $step)\n";
} else if ($step >= 4 && $util['cnt'] > 0) {
    echo "✅ OK: Checked-in with utility records\n";
} else if ($step < 4 && $util['cnt'] == 0) {
    echo "✅ OK: Not checked-in, no utility records\n";
}
?>
