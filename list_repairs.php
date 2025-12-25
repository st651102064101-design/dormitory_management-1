<?php
require_once 'ConnectDB.php';
$pdo = connectDB();

$stmt = $pdo->query('SELECT r.repair_id, c.ctr_id, rm.room_number, t.tnt_name, r.repair_desc, r.repair_status, r.repair_date FROM repair r
LEFT JOIN contract c ON r.ctr_id = c.ctr_id
LEFT JOIN room rm ON c.room_id = rm.room_id
LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
ORDER BY rm.room_number, r.repair_id DESC');

$rooms = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $room = $row['room_number'] ?? 'Unknown';
    if (!isset($rooms[$room])) $rooms[$room] = [];
    $rooms[$room][] = $row;
}

echo "<h2>Repairs by Room</h2>";
foreach ($rooms as $room => $repairs) {
    echo "<h3>Room $room: " . count($repairs) . " repairs</h3>";
    echo "<ul>";
    foreach ($repairs as $r) {
        $status = $r['repair_status'] === '0' ? 'รอซ่อม' : ($r['repair_status'] === '1' ? 'กำลังซ่อม' : 'ซ่อมเสร็จ');
        echo "<li>ID {$r['repair_id']}: {$r['repair_desc']} (status={$status}) - {$r['repair_date']}</li>";
    }
    echo "</ul>";
}
?>
