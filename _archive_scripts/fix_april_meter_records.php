<?php
/**
 * Fix Incorrect April 2026 Meter Records for Room 3 (Payunya)
 * 
 * Context: System incorrectly shows "จดมิเตอร์แล้ว" (meter recorded) for April 2026
 * when no meter should have been recorded yet (from user perspective)
 */

require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

// Find contract ID for Room 3 (Payunya) - Contract ID 5
$findCtrStmt = $pdo->prepare("
    SELECT c.ctr_id, c.tnt_id, t.tnt_name, r.room_number
    FROM contract c
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    WHERE c.ctr_status = '0'
    AND r.room_number = '3'
    AND c.ctr_id = 5
    LIMIT 1
");
$findCtrStmt->execute();
$contract = $findCtrStmt->fetch(PDO::FETCH_ASSOC);

echo "=== April 2026 Meter Record Cleanup ===\n\n";

if ($contract) {
    $ctrId = (int)$contract['ctr_id'];
    echo "✓ Found Contract: ID {$ctrId}\n";
    echo "  Tenant: {$contract['tnt_name']}\n";
    echo "  Room: {$contract['room_number']}\n\n";
    
    // Check for April 2026 records
    $checkStmt = $pdo->prepare("
        SELECT utl_id, utl_date, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
        FROM utility
        WHERE ctr_id = ?
        AND YEAR(utl_date) = 2026
        AND MONTH(utl_date) = 4
    ");
    $checkStmt->execute([$ctrId]);
    $records = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($records)) {
        echo "⚠ Found " . count($records) . " incorrect April 2026 meter record(s):\n";
        foreach ($records as $rec) {
            echo "\n  Record ID: {$rec['utl_id']}\n";
            echo "  Date: {$rec['utl_date']}\n";
            echo "  Water: {$rec['utl_water_start']} → {$rec['utl_water_end']}\n";
            echo "  Electric: {$rec['utl_elec_start']} → {$rec['utl_elec_end']}\n";
        }
        
        echo "\n\n🔧 TO REMOVE THESE RECORDS, RUN:\n\n";
        echo "DELETE FROM utility\n";
        echo "WHERE ctr_id = {$ctrId}\n";
        echo "AND YEAR(utl_date) = 2026\n";
        echo "AND MONTH(utl_date) = 4;\n";
        
        // Option to auto-delete
        echo "\n\nPROCEED WITH AUTO-DELETE? (y/n): ";
        $input = trim(fgets(STDIN));
        
        if (strtolower($input) === 'y') {
            $deleteStmt = $pdo->prepare("
                DELETE FROM utility
                WHERE ctr_id = ?
                AND YEAR(utl_date) = 2026
                AND MONTH(utl_date) = 4
            ");
            $deleteStmt->execute([$ctrId]);
            echo "\n✅ Deleted " . $deleteStmt->rowCount() . " record(s)\n";
            echo "✅ April 2026 is now clean - meter recording can be done fresh\n";
        }
    } else {
        echo "✓ No April 2026 records found - database is clean\n";
        echo "⚠ The issue might be elsewhere\n";
    }
} else {
    echo "✗ Contract not found\n";
    echo "  Check if room 3 exists and contract ID is correct\n";
}
?>
