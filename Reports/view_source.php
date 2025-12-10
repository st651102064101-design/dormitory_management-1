<?php
session_start();
$_SESSION['admin_username'] = 'admin01';
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

$showMode = 'occupied';
$selectedMonth = '12';
$selectedYear = '2025';

// Get rooms like manage_utility.php does
$stmt = $pdo->query("
    SELECT r.room_id, r.room_number, r.room_status, c.ctr_id, t.tnt_name
    FROM room r
    JOIN contract c ON r.room_id = c.room_id AND c.ctr_status IN ('0','1','2')
    JOIN tenant t ON c.tnt_id = t.tnt_id
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
");
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Rooms with Contracts:</h2>";
echo "<table border='1'><tr><th>Room</th><th>room_id</th><th>ctr_id</th><th>Tenant</th></tr>";
foreach ($rooms as $room) {
    echo "<tr>";
    echo "<td>{$room['room_number']}</td>";
    echo "<td>{$room['room_id']}</td>";
    echo "<td><strong>{$room['ctr_id']}</strong></td>";
    echo "<td>{$room['tnt_name']}</td>";
    echo "</tr>";
}
echo "</table>";

// Show sample form HTML that would be generated
echo "<h2>Sample Form for Room 5:</h2>";
$room5 = array_filter($rooms, fn($r) => $r['room_number'] == '5');
$room5 = reset($room5);
if ($room5) {
    $ctrId = $room5['ctr_id'];
    echo "<pre>";
    echo htmlspecialchars('<form class="meter-form" method="post" action="">
    <input type="hidden" name="save_meter" value="1">
    <input type="hidden" name="room_id" value="' . $room5['room_id'] . '">
    <input type="hidden" name="ctr_id" value="' . $ctrId . '">
    ...');
    echo "</pre>";
    echo "<p>ctr_id for room 5 should be: <strong>$ctrId</strong></p>";
}
