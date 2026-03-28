<?php
require 'ConnectDB.php';
$pdo = connectDB();

// Get room 1 contract
$stmt = $pdo->prepare('SELECT c.ctr_id, c.room_id, rt.type_price FROM contract c 
    LEFT JOIN room r ON c.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id 
    WHERE c.room_id = 1 AND c.ctr_status = "0" ORDER BY c.ctr_id DESC LIMIT 1');
$stmt->execute();
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    echo "❌ ไม่พบ contract สำหรับห้อง 1\n";
    exit;
}

$ctrId = $contract['ctr_id'];
$roomPrice = (int)($contract['type_price'] ?? 0);

echo "✓ Contract: $ctrId | Room Price: ฿$roomPrice\n";

// Get current rates
$rateStmt = $pdo->query("SELECT rate_water, rate_elec FROM rate ORDER BY rate_id DESC LIMIT 1");
$rateRow = $rateStmt ? $rateStmt->fetch(PDO::FETCH_ASSOC) : null;
$rateElec = (int)($rateRow['rate_elec'] ?? 8);
$rateWater = (int)($rateRow['rate_water'] ?? 18);

echo "✓ Rates: Water ฿$rateWater | Elec ฿$rateElec\n";

// Get current month
$currentMonth = date('Y-m');
$currentMonthStart = $currentMonth . '-01';

// Check if expense already exists
$checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM expense WHERE ctr_id = ? AND DATE_FORMAT(exp_month, '%Y-%m') = ?");
$checkStmt->execute([$ctrId, $currentMonth]);
$check = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ((int)$check['cnt'] > 0) {
    echo "⚠️  บิลมีอยู่แล้วเดือน $currentMonth\n";
    exit;
}

// Create expense record
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

$expTotal = $roomPrice;
$insertStmt->execute([
    $currentMonthStart,
    $rateElec,
    $rateWater,
    $roomPrice,
    $expTotal,
    $ctrId
]);

echo "\n✅ สร้างบิลเดือน $currentMonth สำเร็จ!\n";
echo "   - ราคาห้อง: ฿$roomPrice\n";
echo "   - ราคาน้ำ: ฿$rateWater/หน่วย\n";
echo "   - ราคาไฟ: ฿$rateElec/หน่วย\n";
?>
