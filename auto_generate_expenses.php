<?php
/**
 * Automated Expense Generation Script
 * This script should be run via cron job on the 1st of every month
 * Cron example: 0 0 1 * * /usr/bin/php /path/to/auto_generate_expenses.php
 */

declare(strict_types=1);

// Prevent direct web access - only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.";
    exit(1);
}

require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    $today = new DateTime();
    $currentMonth = $today->format('Y-m');
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting automated expense generation for month: $currentMonth\n";
    
    // Get all active contracts
    $contractsStmt = $pdo->query("SELECT ctr_id, ctr_start, ctr_end FROM contract WHERE ctr_status = '0'");
    $contracts = $contractsStmt ? $contractsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    echo "Found " . count($contracts) . " active contracts\n";
    
    $generated = 0;
    $skipped = 0;
    
    foreach ($contracts as $contract) {
        $ctr_id = (int)$contract['ctr_id'];
        $ctr_start = (new DateTime($contract['ctr_start']))->format('Y-m');
        $ctr_end = (new DateTime($contract['ctr_end']))->format('Y-m');
        
        // Check if contract is valid for current month
        if ($currentMonth < $ctr_start || $currentMonth > $ctr_end) {
            echo "  [SKIP] Contract $ctr_id: Out of date range (start: $ctr_start, end: $ctr_end)\n";
            $skipped++;
            continue;
        }
        
        // Check if expense already exists for this month
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
        $checkStmt->execute([$ctr_id, $currentMonth]);
        
        if ((int)$checkStmt->fetchColumn() > 0) {
            echo "  [SKIP] Contract $ctr_id: Expense already exists for $currentMonth\n";
            $skipped++;
            continue;
        }
        
        // Get room price
        $roomStmt = $pdo->prepare("
            SELECT r.room_id, rt.type_price 
            FROM contract c 
            LEFT JOIN room r ON c.room_id = r.room_id 
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
            WHERE c.ctr_id = ?
        ");
        $roomStmt->execute([$ctr_id]);
        $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
        $room_price = (int)($room['type_price'] ?? 0);
        
        // Get latest rates
        $rateStmt = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY rate_id DESC LIMIT 1");
        $rateRow = $rateStmt ? $rateStmt->fetch(PDO::FETCH_ASSOC) : null;
        $rate_elec = (int)($rateRow['rate_elec'] ?? 8);
        $rate_water = (int)($rateRow['rate_water'] ?? 18);
        
        // Create new expense record with 0 units (to be filled in later)
        $insertStmt = $pdo->prepare("
            INSERT INTO expense (
                exp_month, 
                exp_elec_unit, 
                exp_water_unit, 
                rate_elec, 
                rate_water, 
                room_price, 
                exp_elec_chg, 
                exp_water, 
                exp_total, 
                exp_status, 
                ctr_id
            ) VALUES (?, 0, 0, ?, ?, ?, 0, 0, ?, '0', ?)
        ");
        
        $exp_total = $room_price;
        
        $insertStmt->execute([
            $currentMonth . '-01',
            $rate_elec,
            $rate_water,
            $room_price,
            $exp_total,
            $ctr_id
        ]);
        
        echo "  [OK] Contract $ctr_id: Generated expense for $currentMonth (Room: ฿$room_price, Rates: Elec ฿$rate_elec/unit, Water ฿$rate_water/unit)\n";
        $generated++;
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Expense generation complete!\n";
    echo "Summary: Generated $generated | Skipped $skipped\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
?>
