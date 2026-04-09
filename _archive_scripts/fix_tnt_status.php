<?php
require 'ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->query("SELECT tnt_id FROM tenant");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tenants as $t) {
    $tnt_id = $t['tnt_id'];
    
    // Check if they have an active contract (0 = normal, 2 = requested cancel but still active)
    $activeContract = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE tnt_id = ? AND ctr_status IN ('0', '2')");
    $activeContract->execute([$tnt_id]);
    $hasActiveContract = $activeContract->fetchColumn() > 0;
    
    if ($hasActiveContract) {
        $pdo->prepare("UPDATE tenant SET tnt_status = '1' WHERE tnt_id = ?")->execute([$tnt_id]);
        continue;
    }
    
    // Check if they have an active booking
    $activeBooking = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE tnt_id = ? AND bkg_status = '1'");
    $activeBooking->execute([$tnt_id]);
    $hasActiveBooking = $activeBooking->fetchColumn() > 0;
    
    if ($hasActiveBooking) {
        $pdo->prepare("UPDATE tenant SET tnt_status = '3' WHERE tnt_id = ?")->execute([$tnt_id]);
        continue;
    }

    // Has a previous contract (but not active)? Then they moved out.
    $anyContract = $pdo->prepare("SELECT COUNT(*) FROM contract WHERE tnt_id = ?");
    $anyContract->execute([$tnt_id]);
    $hasAnyContract = $anyContract->fetchColumn() > 0;

    if ($hasAnyContract) {
        $pdo->prepare("UPDATE tenant SET tnt_status = '0' WHERE tnt_id = ?")->execute([$tnt_id]);
        continue;
    }

    // Has a previous booking (cancelled)?
    $anyBooking = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE tnt_id = ?");
    $anyBooking->execute([$tnt_id]);
    
    if ($anyBooking->fetchColumn() > 0) {
        $pdo->prepare("UPDATE tenant SET tnt_status = '4' WHERE tnt_id = ?")->execute([$tnt_id]);
        continue;
    }
    
    // Otherwise?
    $pdo->prepare("UPDATE tenant SET tnt_status = '0' WHERE tnt_id = ?")->execute([$tnt_id]);
}
echo "Tenant status synced.\n";
