<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Testing Contract Filtering by Month\n";
echo "===================================\n\n";

// Test February 2026
$year = 2026;
$month = 2;

$occupiedSql = "
    SELECT r.room_id, r.room_number, c.ctr_id, c.ctr_start, c.ctr_end, t.tnt_name
    FROM room r
    JOIN (
        SELECT room_id, MAX(ctr_id) AS ctr_id
        FROM contract
        WHERE ctr_status = '0'
        GROUP BY room_id
    ) lc ON r.room_id = lc.room_id
    JOIN contract c ON c.ctr_id = lc.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    WHERE c.ctr_start <= LAST_DAY(STR_TO_DATE(CONCAT(?, '-', ?), '%Y-%m'))
    AND c.ctr_end >= STR_TO_DATE(CONCAT(?, '-', '01'), '%Y-%m-%d')
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
";

try {
    $stmt = $pdo->prepare($occupiedSql);
    $stmt->execute([2026, 2, 2026]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "February 2026 - Rooms with active contracts:\n";
    foreach ($rooms as $room) {
        echo "  Room {$room['room_number']}: {$room['ctr_start']} to {$room['ctr_end']} ({$room['tnt_name']})\n";
    }
    echo "  Total: " . count($rooms) . " rooms\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Test March 2026
try {
    $stmt = $pdo->prepare($occupiedSql);
    $stmt->execute([2026, 3, 2026]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "March 2026 - Rooms with active contracts:\n";
    foreach ($rooms as $room) {
        echo "  Room {$room['room_number']}: {$room['ctr_start']} to {$room['ctr_end']} ({$room['tnt_name']})\n";
    }
    echo "  Total: " . count($rooms) . " rooms\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
