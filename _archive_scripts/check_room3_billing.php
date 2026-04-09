<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "Room 3 - Billing vs Meter Data\n";
echo "================================\n\n";

$stmt = $pdo->prepare("
    SELECT c.ctr_id, c.room_id, c.ctr_start, c.ctr_end, t.tnt_name,
           (SELECT COUNT(*) FROM utility WHERE ctr_id = c.ctr_id) as util_count
    FROM contract c
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    WHERE c.room_id = 3 AND c.ctr_status = '0'
");
$stmt->execute();
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if ($room) {
    echo "Contract Info:\n";
    echo "  Contract ID: {$room['ctr_id']}\n";
    echo "  Tenant: {$room['tnt_name']}\n";
    echo "  Contract Period: {$room['ctr_start']} to {$room['ctr_end']}\n";
    echo "  Billing Start Month: " . date('m/Y', strtotime($room['ctr_start'])) . "\n";
    echo "  Total Utilities: {$room['util_count']}\n\n";
    
    if ($room['util_count'] > 0) {
        echo "Utility Records:\n";
        $utilStmt = $pdo->prepare("
            SELECT utl_id, utl_date, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
            FROM utility
            WHERE ctr_id = ?
            ORDER BY utl_date ASC
        ");
        $utilStmt->execute([$room['ctr_id']]);
        $utils = $utilStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($utils as $u) {
            $billing_month = date('m/Y', strtotime($room['ctr_start']));
            $util_month = date('m/Y', strtotime($u['utl_date']));
            echo "  {$u['utl_date']} ({$util_month}): Water {$u['utl_water_start']}→{$u['utl_water_end']}, Elec {$u['utl_elec_start']}→{$u['utl_elec_end']}\n";
            
            if ($util_month !== $billing_month) {
                echo "    ⚠️  Should be {$billing_month}\n";
            }
        }
    }
} else {
    echo "No active contract found for room 3\n";
}
?>
