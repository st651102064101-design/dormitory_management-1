<?php
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

try {
    // ดึงข้อมูลห้องทั้งหมด
    $stmt = $pdo->query("SELECT r.*, rt.type_name, rt.type_price FROM room r LEFT JOIN roomtype rt ON r.type_id = rt.type_id ORDER BY CAST(r.room_number AS UNSIGNED) ASC");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Room Data Check</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Room ID</th><th>Room Number</th><th>Type</th><th>Status</th><th>Image</th><th>Has Image?</th></tr>";
    
    foreach ($rooms as $room) {
        $hasImage = !empty($room['room_image']) ? 'YES' : 'NO (will use SVG)';
        $imageFile = !empty($room['room_image']) ? $room['room_image'] : 'N/A';
        
        echo "<tr>";
        echo "<td>" . $room['room_id'] . "</td>";
        echo "<td>" . $room['room_number'] . "</td>";
        echo "<td>" . ($room['type_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($room['room_status'] == '0' ? 'Available' : 'Occupied') . "</td>";
        echo "<td>" . htmlspecialchars($imageFile) . "</td>";
        echo "<td>" . $hasImage . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<p>Total Rooms: " . count($rooms) . "</p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
