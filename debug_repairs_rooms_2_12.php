<?php
/**
 * Debug script to find repair records for rooms 2 and 12
 */
declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    echo "<h2>🔍 Debug: Repairs for Rooms 2 and 12</h2>";
    echo "<pre style='background:#f5f5f5;padding:1rem;border-radius:8px;'>";
    
    // 1. Find contracts for rooms 2 and 12
    echo "\n=== STEP 1: Find Contracts for Rooms 2 and 12 ===\n";
    $stmt = $pdo->query("
        SELECT c.ctr_id, c.tnt_id, c.room_id, r.room_number, t.tnt_name, c.ctr_status
        FROM contract c
        JOIN room r ON c.room_id = r.room_id
        JOIN tenant t ON c.tnt_id = t.tnt_id
        WHERE r.room_number IN (2, 12)
        ORDER BY r.room_number
    ");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($contracts) . " contract(s):\n";
    foreach ($contracts as $c) {
        echo "  - Room {$c['room_number']}: ctr_id={$c['ctr_id']}, tnt_id={$c['tnt_id']}, room_id={$c['room_id']}, " .
             "tenant={$c['tnt_name']}, status={$c['ctr_status']}\n";
    }
    
    // 2. Find repairs for those contract IDs
    echo "\n=== STEP 2: Find Repairs for those Contract IDs ===\n";
    $ctrIds = array_column($contracts, 'ctr_id');
    if (!empty($ctrIds)) {
        $placeholders = implode(',', array_fill(0, count($ctrIds), '?'));
        $stmt = $pdo->prepare("
            SELECT repair_id, ctr_id, repair_date, repair_desc, repair_status
            FROM repair
            WHERE ctr_id IN ($placeholders)
            ORDER BY repair_date DESC
        ");
        $stmt->execute($ctrIds);
        $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Found " . count($repairs) . " repair record(s):\n";
        foreach ($repairs as $r) {
            echo "  - repair_id={$r['repair_id']}, ctr_id={$r['ctr_id']}, date={$r['repair_date']}, " .
                 "status={$r['repair_status']}, desc='" . substr($r['repair_desc'], 0, 50) . "...'\n";
        }
    } else {
        echo "No contracts found for rooms 2 and 12!\n";
    }
    
    // 3. Check ALL repairs with NULL room_number (broken JOINs)
    echo "\n=== STEP 3: Check ALL Repairs with NULL room_number ===\n";
    $stmt = $pdo->query("
        SELECT r.repair_id, r.ctr_id, r.repair_date, rm.room_number, t.tnt_name
        FROM repair r
        LEFT JOIN contract c ON r.ctr_id = c.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room rm ON c.room_id = rm.room_id
        WHERE rm.room_number IS NULL
        ORDER BY r.repair_date DESC
        LIMIT 10
    ");
    $orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($orphaned) . " repair record(s) with NULL room_number:\n";
    foreach ($orphaned as $o) {
        echo "  - repair_id={$o['repair_id']}, ctr_id={$o['ctr_id']}, date={$o['repair_date']}, " .
             "tenant={$o['tnt_name']}\n";
    }
    
    // 4. Check if those repairs are actually in database but with wrong ctr_id
    echo "\n=== STEP 4: Direct check for ALL repairs in database ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM repair");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total repairs in database: {$total['total']}\n";
    
    // 5. Show repairs with full JOIN info
    echo "\n=== STEP 5: Sample of ALL repairs with JOIN details ===\n";
    $stmt = $pdo->query("
        SELECT r.repair_id, r.ctr_id, r.repair_date, r.repair_desc, r.repair_status,
               c.ctr_id as contract_ctr_id, 
               t.tnt_name, 
               rm.room_number
        FROM repair r
        LEFT JOIN contract c ON r.ctr_id = c.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room rm ON c.room_id = rm.room_id
        ORDER BY r.repair_date DESC
        LIMIT 15
    ");
    $allRepairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Showing first 15 repairs:\n";
    foreach ($allRepairs as $a) {
        $room = $a['room_number'] ?? 'NULL';
        echo "  - ID={$a['repair_id']}, r.ctr_id={$a['ctr_id']}, c.ctr_id={$a['contract_ctr_id']}, " .
             "room={$room}, tenant={$a['tnt_name']}, date={$a['repair_date']}\n";
    }
    
    echo "\n</pre>";
    
} catch (Exception $e) {
    echo "<div style='background:#fee;padding:1rem;border-radius:8px;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
