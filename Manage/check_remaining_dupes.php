<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT tnt_id, room_id, COUNT(*) as c, GROUP_CONCAT(ctr_id ORDER BY ctr_id) as ids FROM contract WHERE ctr_status IN ('0','2') GROUP BY tnt_id, room_id HAVING c > 1 ORDER BY c DESC");
echo "📊 Duplicates Remaining:\n\n";
$n = 0;
foreach ($stmt as $row) {
    $n++;
    echo "$n. Tenant: {$row['tnt_id']}, Room: {$row['room_id']}\n   IDs: {$row['ids']} (Count: {$row['c']})\n\n";
}
echo "Total Duplicate Sets: $n\n";
?>
