<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Room 12 Status Check\n";
echo "===================\n\n";

$stmt = $pdo->prepare("
    SELECT c.ctr_id, c.room_id, c.ctr_start, c.ctr_end, t.tnt_name,
           tw.current_step,
           (SELECT COUNT(*) FROM checkin_record WHERE ctr_id = c.ctr_id) as checkin_count,
           (SELECT COUNT(*) FROM utility WHERE ctr_id = c.ctr_id) as utility_count
    FROM contract c
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN tenant_workflow tw ON c.ctr_id = tw.ctr_id
    WHERE c.room_id = 12 AND c.ctr_status = '0'
");
$stmt->execute();
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if ($room) {
    echo "Contract ID: {$room['ctr_id']}\n";
    echo "Tenant: {$room['tnt_name']}\n";
    echo "Contract: {$room['ctr_start']} to {$room['ctr_end']}\n";
    echo "Workflow Step: {$room['current_step']}/5\n";
    echo "Checkin Records: {$room['checkin_count']}\n";
    echo "Utility Records: {$room['utility_count']}\n\n";
    
    // Check if meter should be blocked
    $shouldBlock = $room['current_step'] <= 3 || ($room['current_step'] === 4 && $room['checkin_count'] === 0);
    echo "Should block meter: " . ($shouldBlock ? "YES" : "NO") . "\n";
    echo "  - Step <= 3: " . ($room['current_step'] <= 3 ? "YES" : "NO") . "\n";
    echo "  - Step 4 without checkin: " . ($room['current_step'] === 4 && $room['checkin_count'] === 0 ? "YES" : "NO") . "\n";
} else {
    echo "No active contract found for room 12\n";
}
?>
