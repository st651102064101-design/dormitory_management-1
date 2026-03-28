<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

$year = 2026;
$month = 3;

$sql = "
    SELECT r.room_id, r.room_number, c.ctr_id, t.tnt_name, c.ctr_start, c.ctr_end
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

$stmt = $pdo->prepare($sql);
$stmt->execute([2026, 3, 2026]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "March 2026 - Current Active Rooms:\n";
foreach ($rooms as $r) {
    echo "Room {$r['room_number']}: {$r['ctr_start']} to {$r['ctr_end']} ({$r['tnt_name']})\n";
}
echo "Total: " . count($rooms) . " rooms\n";
?>
