<?php
/**
 * Update All Expenses Rate - อัปเดตอัตราค่าน้ำค่าไฟในบิลทั้งหมดให้ใช้อัตราปัจจุบัน
 */
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['success' => false, 'message' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'invalid method']);
    exit;
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    require_once __DIR__ . '/../includes/water_calc.php';
    $pdo = connectDB();

    // Get the latest (active) rate
    $stmt = $pdo->query("SELECT * FROM rate ORDER BY effective_date DESC, rate_id DESC LIMIT 1");
    $currentRate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentRate) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบอัตราปัจจุบัน']);
        exit;
    }

    $newRateWater = (int)$currentRate['rate_water'];
    $newRateElec  = (int)$currentRate['rate_elec'];
    $baseUnits    = $currentRate['water_base_units'] !== null ? (int)$currentRate['water_base_units'] : WATER_BASE_UNITS;
    $basePrice    = $currentRate['water_base_price'] !== null ? (int)$currentRate['water_base_price'] : WATER_BASE_PRICE;
    $excessRate   = $currentRate['water_excess_rate'] !== null ? (int)$currentRate['water_excess_rate'] : WATER_EXCESS_RATE;

    // Count how many expense rows have different rates
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM expense WHERE rate_water != ? OR rate_elec != ?");
    $countStmt->execute([$newRateWater, $newRateElec]);
    $toUpdate = (int)$countStmt->fetchColumn();

    if ($toUpdate === 0) {
        echo json_encode([
            'success' => true,
            'updated' => 0,
            'message' => 'บิลทั้งหมดใช้อัตราปัจจุบันอยู่แล้ว'
        ]);
        exit;
    }

    // Update all expenses to use the current rate
    // Also recalculate water charge and total based on new rates
    $pdo->beginTransaction();

    // Get all expenses that need updating
    $expStmt = $pdo->query("SELECT exp_id, exp_water_unit, exp_elec_unit, room_price, rate_water, rate_elec FROM expense WHERE rate_water != {$newRateWater} OR rate_elec != {$newRateElec}");
    $expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("UPDATE expense SET rate_water = ?, rate_elec = ?, exp_water = ?, exp_elec_chg = ?, exp_total = ? WHERE exp_id = ?");

    $updated = 0;
    foreach ($expenses as $exp) {
        $waterUnits = (int)$exp['exp_water_unit'];
        $elecUnits  = (int)$exp['exp_elec_unit'];
        $roomPrice  = (int)$exp['room_price'];

        // Calculate water charge using base-unit pricing
        if ($waterUnits <= $baseUnits) {
            $waterCharge = $basePrice;
        } else {
            $waterCharge = $basePrice + (($waterUnits - $baseUnits) * $excessRate);
        }

        // Calculate electric charge
        $elecCharge = $elecUnits * $newRateElec;

        // Calculate total
        $total = $roomPrice + $waterCharge + $elecCharge;

        $updateStmt->execute([
            $newRateWater,
            $newRateElec,
            $waterCharge,
            $elecCharge,
            $total,
            $exp['exp_id']
        ]);
        $updated++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'rate_water' => $newRateWater,
        'rate_elec' => $newRateElec,
        'water_base_units' => $baseUnits,
        'water_base_price' => $basePrice,
        'water_excess_rate' => $excessRate,
        'message' => "อัปเดตบิลสำเร็จ {$updated} รายการ ให้ใช้อัตราปัจจุบัน (ค่าน้ำเหมา ฿{$basePrice}/≤{$baseUnits} หน่วย, เกินหน่วยละ ฿{$excessRate}, ค่าไฟ ฿{$newRateElec}/หน่วย)"
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[update_all_rates] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
