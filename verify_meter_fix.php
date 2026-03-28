<?php
require '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/ConnectDB.php';
$pdo = connectDB();

echo "📊 Meter Display Verification After Fix\n";
echo "========================================\n\n";

try {
    // Check Room 1 specifically
    $stmt = $pdo->prepare(
        "SELECT c.ctr_id, c.room_id, tw.current_step, 
                COUNT(DISTINCT ch.checkin_id) as checkin_count,
                COUNT(DISTINCT u.utl_id) as utility_count
         FROM contract c
         INNER JOIN tenant_workflow tw ON c.ctr_id = tw.ctr_id
         LEFT JOIN checkin_record ch ON c.ctr_id = ch.ctr_id
         LEFT JOIN utility u ON c.ctr_id = u.ctr_id
         WHERE c.room_id = 1
         GROUP BY c.ctr_id"
    );
    $stmt->execute();
    $room1 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Room 1 Status:\n";
    if ($room1) {
        echo "  Contract ID: {$room1['ctr_id']}\n";
        echo "  Workflow Step: {$room1['current_step']}/5\n";
        echo "  Checkin Records: {$room1['checkin_count']}\n";
        echo "  Utility Records: {$room1['utility_count']}\n";
        echo "  Display Status: ";
        
        if ($room1['current_step'] <= 3) {
            echo "❌ Blocked (Step <= 3)\n";
        } elseif ($room1['current_step'] === 4 && $room1['checkin_count'] === 0) {
            echo "❌ Blocked (Step 4 but no checkin)\n";
        } else {
            echo "✅ Allowed (Step {$room1['current_step']} + checkin exists)\n";
        }
    } else {
        echo "  No contract found\n";
    }
    
    echo "\n📋 All Rooms Status:\n";
    echo "============================\n";
    
    $allStmt = $pdo->query(
        "SELECT c.room_id, c.ctr_id, tw.current_step, 
                COUNT(DISTINCT ch.checkin_id) as checkin_count,
                COUNT(DISTINCT u.utl_id) as utility_count
         FROM contract c
         INNER JOIN tenant_workflow tw ON c.ctr_id = tw.ctr_id
         LEFT JOIN checkin_record ch ON c.ctr_id = ch.ctr_id
         LEFT JOIN utility u ON c.ctr_id = u.ctr_id
         GROUP BY c.ctr_id, c.room_id, tw.current_step
         ORDER BY c.room_id"
    );
    
    $allRooms = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allRooms as $room) {
        $step = $room['current_step'];
        $checkin = $room['checkin_count'];
        $util = $room['utility_count'];
        
        if ($step <= 3) {
            $status = "❌ Blocked";
        } elseif ($step === 4 && $checkin === 0) {
            $status = "❌ Blocked";
        } else {
            $status = "✅ Allowed";
        }
        
        printf("  Room %2d: Step %d | Checkin: %d | Utility: %d | %s\n",
               $room['room_id'], $step, $checkin, $util, $status);
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
