#!/usr/bin/env php
<?php
/**
 * Debug Script: Check meter status for Room 3 (Payunya) 
 * Trace why system shows "จดมิเตอร์แล้ว" (meter recorded)
 */

require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "\n=== DEBUG: Room 3 Meter Status ===\n";
echo "Current Date: " . date('Y-m-d (F)') . "\n";
echo "Current Month: " . date('n (F)') . "\n\n";

// Find Contract
$ctr = $pdo->query("
    SELECT c.ctr_id, c.tnt_id, t.tnt_name, r.room_number, c.ctr_start
    FROM contract c
    LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
    LEFT JOIN room r ON c.room_id = r.room_id
    WHERE r.room_number = '3' AND c.ctr_status = '0'
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$ctr) {
    echo "✗ No active contract found for room 3\n";
    exit(1);
}

$ctrId = (int)$ctr['ctr_id'];
echo "✓ Contract Found:\n";
echo "  ID: {$ctr['ctr_id']}\n";
echo "  Tenant: {$ctr['tnt_name']}\n";
echo "  Room: {$ctr['room_number']}\n";
echo "  Start Date: {$ctr['ctr_start']}\n\n";

// Check all utility records for this contract
$allRecords = $pdo->query("
    SELECT utl_id, ctr_id, DATE_FORMAT(utl_date, '%Y-%m') AS month_year,
           YEAR(utl_date) AS year, MONTH(utl_date) AS month,
           utl_water_start, utl_water_end, utl_elec_start, utl_elec_end
    FROM utility
    WHERE ctr_id = {$ctrId}
    ORDER BY utl_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "Utility Records:\n";
if (empty($allRecords)) {
    echo "  (No records)\n";
} else {
    foreach ($allRecords as $r) {
        $highlight = ($r['month_year'] === '2026-04') ? ' ⚠️ APRIL 2026' : '';
        echo "  [{$r['utl_id']}] {$r['month_year']}{$highlight}\n";
        echo "        Water: {$r['utl_water_start']} → {$r['utl_water_end']}\n";
        echo "        Elec:  {$r['utl_elec_start']} → {$r['utl_elec_end']}\n";
    }
}

// Check expense records too
echo "\nExpense Records (for context):\n";
$expenses = $pdo->query("
    SELECT exp_id, DATE_FORMAT(exp_month, '%Y-%m') AS month_year,
           exp_month, exp_elec_unit, exp_water_unit
    FROM expense
    WHERE ctr_id = {$ctrId}
    ORDER BY exp_month DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($expenses)) {
    echo "  (No records)\n";
} else {
    foreach ($expenses as $e) {
        echo "  [{$e['exp_id']}] {$e['month_year']}\n";
        echo "        Water: {$e['exp_water_unit']} units\n";
        echo "        Elec: {$e['exp_elec_unit']} units\n";
    }
}

// Simulate what tenant_wizard.php sees
echo "\n\n=== SIMULATING tenant_wizard.php LOGIC ===\n";
$firstExpMonth = $pdo->query(
    "SELECT first_exp_month FROM contract WHERE ctr_id = {$ctrId}"
)->fetchColumn();

echo "first_exp_month from contract table: " . ($firstExpMonth ? "{$firstExpMonth}" : "NULL") . "\n";

if ($firstExpMonth) {
    $billYearMonth = date('Y-m', strtotime($firstExpMonth));
    $prevYearMonth = date('Y-m', strtotime($billYearMonth . '-01 -1 month'));
    
    echo "Calculated Bill Year-Month: {$billYearMonth}\n";
    echo "Calculated Prev Year-Month: {$prevYearMonth}\n";
    
    // Check if bill month is recorded
    $billRecorded = $pdo->query(
        "SELECT COUNT(*) FROM utility WHERE ctr_id = {$ctrId} 
         AND DATE_FORMAT(utl_date, '%Y-%m') = '{$billYearMonth}'"
    )->fetchColumn();
    
    echo "\nMeter recorded for bill month ({$billYearMonth})?: " . ($billRecorded ? "YES ✓" : "NO ✗") . "\n";
    echo "\n→ This means: meterBillDone = " . ($billRecorded ? "TRUE" : "FALSE") . "\n";
    echo "→ UI will show: " . ($billRecorded ? "\"✓ จดมิเตอร์แล้ว\"" : "\"บันทึกมิเตอร์\" button") . "\n";
}

echo "\n=== END DEBUG ===\n\n";
?>
