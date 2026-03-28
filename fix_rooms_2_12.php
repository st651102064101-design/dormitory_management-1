<?php
/**
 * Fix: Ensure all rooms exist and repairs display correctly
 */
declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    echo "<h2>🔧 Fixing Room and Repair Records</h2>";
    echo "<pre style='background:#f5f5f5;padding:1rem;border-radius:8px;'>";
    
    // 1. Get count of rooms to know total capacity
    $roomCount = $pdo->query("SELECT COUNT(*) FROM room")->fetchColumn();
    echo "Current rooms in database: $roomCount\n";
    
    // 2. Find which room IDs are missing
    echo "\nChecking for missing room records...\n";
    $allRooms = $pdo->query("SELECT room_number FROM room ORDER BY room_number")->fetchAll(PDO::FETCH_COLUMN);
    $missing = [];
    for ($i = 1; $i <= (int)$roomCount + 5; $i++) {
        if (!in_array($i, $allRooms)) {
            $missing[] = $i;
        }
    }
    
    if (!empty($missing)) {
        echo "Missing room numbers: " . implode(', ', $missing) . "\n";
    }
    
    // 3. Check if rooms 2 and 12 exist
    echo "\nChecking rooms 2 and 12...\n";
    $room2 = $pdo->query("SELECT room_id FROM room WHERE room_number = 2")->fetchColumn();
    $room12 = $pdo->query("SELECT room_id FROM room WHERE room_number = 12")->fetchColumn();
    
    if (!$room2) {
        echo "  ⚠️ Room 2 missing - creating...\n";
        // Find a default room type
        $typeId = $pdo->query("SELECT type_id FROM roomtype LIMIT 1")->fetchColumn() ?? 1;
        $stmt = $pdo->prepare("INSERT INTO room (room_number, type_id) VALUES (2, ?)");
        $stmt->execute([$typeId]);
        echo "  ✓ Room 2 created\n";
    } else {
        echo "  ✓ Room 2 exists (room_id=$room2)\n";
    }
    
    if (!$room12) {
        echo "  ⚠️ Room 12 missing - creating...\n";
        // Find a default room type
        $typeId = $pdo->query("SELECT type_id FROM roomtype LIMIT 1")->fetchColumn() ?? 1;
        $stmt = $pdo->prepare("INSERT INTO room (room_number, type_id) VALUES (12, ?)");
        $stmt->execute([$typeId]);
        echo "  ✓ Room 12 created\n";
    } else {
        echo "  ✓ Room 12 exists (room_id=$room12)\n";
    }
    
    // 4. Test the repair query after fix
    echo "\n=== Testing repair query after fix ===\n";
    $stmt = $pdo->query("
        SELECT r.repair_id, r.ctr_id, r.repair_date, r.repair_desc, r.repair_status,
               c.ctr_id as check_ctr_id, 
               t.tnt_name, 
               rm.room_number
        FROM repair r
        LEFT JOIN contract c ON r.ctr_id = c.ctr_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        LEFT JOIN room rm ON c.room_id = rm.room_id
        WHERE rm.room_number IN (2, 12) OR (rm.room_number IS NULL AND r.repair_date > DATE_SUB(NOW(), INTERVAL 30 DAY))
        ORDER BY r.repair_date DESC
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "No repairs found for rooms 2 and 12 or recent orphaned repairs\n";
    } else {
        echo "Found " . count($results) . " repair record(s):\n";
        foreach ($results as $result) {
            $room = $result['room_number'] ?? 'NULL';
            echo "  - repair_id={$result['repair_id']}, ctr_id={$result['ctr_id']}, " .
                 "room={$room}, date={$result['repair_date']}, status={$result['repair_status']}\n";
        }
    }
    
    echo "\n✅ Fix complete!\n";
    echo "\n</pre>";
    
    echo "<div style='background:#dcf;padding:1rem;border-radius:8px;margin-top:1rem;'>";
    echo "<strong>✓ Done!</strong> Rooms 2 and 12 are now properly created in the database.<br>";
    echo "<a href='/dormitory_management/Reports/manage_repairs.php' style='color:#00f;text-decoration:underline;'>Go back to Manage Repairs</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background:#fee;padding:1rem;border-radius:8px;'>";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
