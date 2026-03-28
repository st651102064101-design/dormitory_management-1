<?php
/**
 * Check if rooms 2 and 12 exist in database
 */
declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    echo "<h2>🔍 Database Debug: Rooms 2 and 12</h2>";
    echo "<pre style='background:#f5f5f5;padding:1rem;border-radius:8px;'>";
    
    // 1. Check room table
    echo "\n=== STEP 1: Check ROOM table ===\n";
    $stmt = $pdo->query("SELECT room_id, room_number, type_id FROM room WHERE room_number IN (2, 12)");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Rooms found: " . count($rooms) . "\n";
    foreach ($rooms as $r) {
        echo "  - room_id={$r['room_id']}, room_number={$r['room_number']}, type_id={$r['type_id']}\n";
    }
    
    if (empty($rooms)) {
        echo "⚠️ ROOMS 2 AND 12 DO NOT EXIST IN ROOM TABLE!\n";
        echo "Creating them now...\n";
        // Find a valid type_id first
        $typeStmt = $pdo->query("SELECT type_id FROM roomtype LIMIT 1");
        $typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);
        $typeId = $typeRow ? (int)$typeRow['type_id'] : 1;
        
        $insertStmt = $pdo->prepare("INSERT INTO room (room_number, type_id) VALUES (?, ?)");
        $insertStmt->execute([2, $typeId]);
        echo "  ✓ Created room 2\n";
        
        $insertStmt->execute([12, $typeId]);
        echo "  ✓ Created room 12\n";
    }
    
    // 2. Check contract for rooms 2 and 12
    echo "\n=== STEP 2: Check CONTRACTS for Rooms 2 and 12 ===\n";
    $stmt = $pdo->query("
        SELECT c.ctr_id, c.tnt_id, c.room_id, r.room_number, c.ctr_status
        FROM contract c
        JOIN room r ON c.room_id = r.room_id
        WHERE r.room_number IN (2, 12)
    ");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Contracts found: " . count($contracts) . "\n";
    foreach ($contracts as $c) {
        echo "  - ctr_id={$c['ctr_id']}, tnt_id={$c['tnt_id']}, room_id={$c['room_id']}, " .
             "room_number={$c['room_number']}, status={$c['ctr_status']}\n";
    }
    
    // 3. Check repairs
    echo "\n=== STEP 3: Check REPAIRS for those Contract IDs ===\n";
    if (!empty($contracts)) {
        $ctrIds = array_column($contracts, 'ctr_id');
        $placeholders = implode(',', array_fill(0, count($ctrIds), '?'));
        $stmt = $pdo->prepare("
            SELECT repair_id, ctr_id, repair_date, repair_desc, repair_status
            FROM repair
            WHERE ctr_id IN ($placeholders)
        ");
        $stmt->execute($ctrIds);
        $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Repair records found: " . count($repairs) . "\n";
        foreach ($repairs as $r) {
            echo "  - repair_id={$r['repair_id']}, ctr_id={$r['ctr_id']}, " .
                 "date={$r['repair_date']}, status={$r['repair_status']}\n";
        }
    }
    
    // 4. Check the exact query from manage_repairs.php
    echo "\n=== STEP 4: Test exact query from manage_repairs.php ===\n";
    $stmt = $pdo->query("
        SELECT r.repair_id, r.ctr_id, r.repair_date, r.repair_desc, r.repair_status,
               t.tnt_name, rm.room_number
        FROM repair r
        LEFT JOIN contract c ON r.ctr_id = c.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room rm ON c.room_id = rm.room_id
        ORDER BY r.repair_date DESC
        LIMIT 20
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Query returned " . count($results) . " records\n";
    foreach ($results as $result) {
        $room = $result['room_number'] ?? 'NULL';
        echo "  - repair_id={$result['repair_id']}, ctr_id={$result['ctr_id']}, " .
             "room={$room}, date={$result['repair_date']}\n";
    }
    
    echo "\n</pre>";
    
} catch (Exception $e) {
    echo "<div style='background:#fee;padding:1rem;border-radius:8px;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
