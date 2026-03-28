<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "All Data for Room 12\n";
echo "====================\n\n";

echo "1. Contracts:\n";
$stmt = $pdo->query("SELECT ctr_id, room_id, tnt_id, ctr_start, ctr_end, ctr_status FROM contract WHERE room_id = 12 ORDER BY ctr_id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "  No contracts\n";
} else {
    foreach ($rows as $row) {
        $status = $row['ctr_status'] === '0' ? 'ACTIVE' : 'CANCELLED';
        echo "  CTR {$row['ctr_id']}: {$row['ctr_start']} to {$row['ctr_end']} (Status: $status)\n";
    }
}

echo "\n2. Utility records:\n";
$stmt = $pdo->query("SELECT u.utl_id, u.ctr_id, u.utl_date, u.utl_water_start, u.utl_water_end FROM utility WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE room_id = 12) ORDER BY utl_date DESC");
$utils = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($utils)) {
    echo "  No utility records\n";
} else {
    foreach ($utils as $u) {
        echo "  Util {$u['utl_id']}: CTR {$u['ctr_id']}, Date: {$u['utl_date']}, Water: {$u['utl_water_start']} → {$u['utl_water_end']}\n";
    }
}

echo "\n3. Workflow:\n";
$stmt = $pdo->query("SELECT tw.ctr_id, tw.current_step FROM tenant_workflow tw WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE room_id = 12)");
$workflows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($workflows)) {
    echo "  No workflow records\n";
} else {
    foreach ($workflows as $w) {
        echo "  CTR {$w['ctr_id']}: Step {$w['current_step']}/5\n";
    }
}

echo "\n4. Checkin:\n";
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM checkin_record WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE room_id = 12)");
$checkin = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Checkin records: {$checkin['cnt']}\n";
?>
