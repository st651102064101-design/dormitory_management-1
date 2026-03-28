<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

$year = 2026;
$month = 2;

echo "Testing SQL Query\n";
echo "=================\n\n";

$occupiedSql = "
    SELECT r.room_id, r.room_number, c.ctr_id, t.tnt_name, COALESCE(tw.current_step, 1) AS workflow_step
    FROM room r
    JOIN (
        SELECT room_id, MAX(ctr_id) AS ctr_id
        FROM contract
        WHERE ctr_status = '0'
        GROUP BY room_id
    ) lc ON r.room_id = lc.room_id
    JOIN contract c ON c.ctr_id = lc.ctr_id
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN tenant_workflow tw ON c.tnt_id = tw.tnt_id
    WHERE c.ctr_start <= LAST_DAY(STR_TO_DATE(CONCAT(?, '-', ?), '%Y-%m'))
    AND c.ctr_end >= STR_TO_DATE(CONCAT(?, '-', '01'), '%Y-%m-%d')
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
";

echo "Month: $month, Year: $year\n";
echo "SQL: " . substr($occupiedSql, 0, 100) . "...\n\n";

try {
    $occupiedStmt = $pdo->prepare($occupiedSql);
    $occupiedStmt->execute([$year, $month, $year]);
    $rooms = $occupiedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Query Success!\n";
    echo "Rooms found: " . count($rooms) . "\n\n";
    
    foreach ($rooms as $room) {
        echo "  Room {$room['room_number']} (ID: {$room['room_id']}, CTR: {$room['ctr_id']})\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
