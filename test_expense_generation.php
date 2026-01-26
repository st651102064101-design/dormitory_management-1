<?php
/**
 * Test Script for Automated Expense Generation
 * Usage: /usr/bin/php test_expense_generation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    echo "========================================\n";
    echo "Testing Automated Expense Generation\n";
    echo "========================================\n\n";
    
    $currentMonth = '2026-01'; // Current month
    
    echo "📋 Processing month: $currentMonth\n";
    echo "Getting all active contracts...\n\n";
    
    // Get all active contracts
    $contractsStmt = $pdo->query("SELECT ctr_id, ctr_start, ctr_end FROM contract WHERE ctr_status = '0'");
    $contracts = $contractsStmt ? $contractsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    echo "Found " . count($contracts) . " active contracts\n\n";
    
    $generated = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($contracts as $contract) {
        $ctr_id = (int)$contract['ctr_id'];
        $ctr_start = (new DateTime($contract['ctr_start']))->format('Y-m');
        $ctr_end = (new DateTime($contract['ctr_end']))->format('Y-m');
        
        echo "---\n";
        echo "Processing Contract ID: $ctr_id\n";
        echo "  Start: $ctr_start, End: $ctr_end\n";
        
        // Check if contract is valid for current month
        if ($currentMonth < $ctr_start || $currentMonth > $ctr_end) {
            echo "  ⏭️  Result: Out of date range (SKIPPED)\n";
            $skipped++;
            continue;
        }
        
        // Check if expense already exists for this month
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
        $checkStmt->execute([$ctr_id, $currentMonth]);
        $existCount = (int)$checkStmt->fetchColumn();
        
        if ($existCount > 0) {
            echo "  ⏭️  Result: Already exists for $currentMonth (SKIPPED)\n";
            $skipped++;
            continue;
        }
        
        echo "  ✓ Validation passed\n";
        
        try {
            // Get contract and room info
            $contractStmt = $pdo->prepare("
                SELECT c.ctr_id, r.room_number, rt.type_price, t.tnt_name
                FROM contract c 
                LEFT JOIN room r ON c.room_id = r.room_id 
                LEFT JOIN roomtype rt ON r.type_id = rt.type_id
                LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
                WHERE c.ctr_id = ?
            ");
            $contractStmt->execute([$ctr_id]);
            $contractInfo = $contractStmt->fetch(PDO::FETCH_ASSOC);
            
            $room_number = $contractInfo['room_number'] ?? 'N/A';
            $tenant_name = $contractInfo['tnt_name'] ?? 'Unknown';
            $room_price = (int)($contractInfo['type_price'] ?? 0);
            
            echo "  Room: $room_number, Tenant: $tenant_name, Price: ฿$room_price\n";
            
            // Get latest rates
            $rateStmt = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY rate_id DESC LIMIT 1");
            $rateRow = $rateStmt ? $rateStmt->fetch(PDO::FETCH_ASSOC) : null;
            $rate_elec = (int)($rateRow['rate_elec'] ?? 8);
            $rate_water = (int)($rateRow['rate_water'] ?? 18);
            
            echo "  Rates: Electric ฿$rate_elec/unit, Water ฿$rate_water/unit\n";
            
            // Create new expense record with 0 units
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
            
            echo "  ✅ Result: Created successfully\n";
            $generated++;
        } catch (Exception $e) {
            echo "  ❌ Result: " . $e->getMessage() . "\n";
            $errors[] = "Contract $ctr_id: " . $e->getMessage();
        }
    }
    
    echo "\n========================================\n";
    echo "✅ TEST COMPLETE\n";
    echo "========================================\n";
    echo "Generated: $generated\n";
    echo "Skipped: $skipped\n";
    
    if (!empty($errors)) {
        echo "\n⚠️  Errors (" . count($errors) . "):\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    echo "\n";
    
    // Show the created records
    echo "Created Expense Records:\n";
    echo "---\n";
    
    $expenseStmt = $pdo->prepare("
        SELECT e.exp_id, r.room_number, t.tnt_name, e.exp_month, e.room_price, e.rate_elec, e.rate_water
        FROM expense e
        LEFT JOIN contract c ON e.ctr_id = c.ctr_id
        LEFT JOIN room r ON c.room_id = r.room_id
        LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
        WHERE DATE_FORMAT(e.exp_month, '%Y-%m') = ?
        ORDER BY r.room_number
    ");
    $expenseStmt->execute([$currentMonth]);
    $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($expenses)) {
        foreach ($expenses as $exp) {
            echo "ID: " . str_pad((string)$exp['exp_id'], 4, '0', STR_PAD_LEFT);
            echo " | Room: " . $exp['room_number'];
            echo " | Tenant: " . $exp['tnt_name'];
            echo " | Price: ฿" . $exp['room_price'];
            echo " | Elec Rate: ฿" . $exp['rate_elec'];
            echo " | Water Rate: ฿" . $exp['rate_water'];
            echo "\n";
        }
    } else {
        echo "No expense records found for $currentMonth\n";
    }
    
    exit(0);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
