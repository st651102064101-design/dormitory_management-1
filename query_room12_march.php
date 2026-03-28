<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

$year = 2026;
$month = 3; // March

echo "Query from manage_utility for March 2026, Room 12:\n";
echo "=================================================\n\n";

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
    AND r.room_id = 12
    ORDER BY CAST(r.room_number AS UNSIGNED) ASC
";

$stmt = $pdo->prepare($occupiedSql);
$stmt->execute([$year, $month, $year]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if ($room) {
    echo "Room found:\n";
    echo "  Room ID: {$room['room_id']}\n";
    echo "  Room Number: {$room['room_number']}\n";
    echo "  Contract ID: {$room['ctr_id']}\n";
    echo "  Tenant: {$room['tnt_name']}\n";
    echo "  Workflow Step: {$room['workflow_step']}\n\n";
    
    // Now check what utilities exist for this contract
    $utilStmt = $pdo->prepare("SELECT * FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = ? AND YEAR(utl_date) = ?");
    $utilStmt->execute([$room['ctr_id'], $month, $year]);
    $utilities = $utilStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Utilities for March 2026:\n";
    echo "  Count: " . count($utilities) . "\n";
    foreach ($utilities as $u) {
        echo "  Water: {$u['utl_water_start']} → {$u['utl_water_end']}\n";
    }
} else {
    echo "No room found\n";
}
?>
