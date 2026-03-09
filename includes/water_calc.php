<?php
/**
 * Water Cost Calculation - ระบบคำนวณค่าน้ำแบบเหมาจ่าย
 * 
 * - ใช้ <= 10 หน่วย → จ่าย 200 บาท (เหมาจ่าย)
 * - ใช้ > 10 หน่วย → 200 + (หน่วยที่เกิน × 25) บาท
 * - ยังไม่มีข้อมูลมิเตอร์ (0 หน่วย) → 0 บาท
 */

// ค่าคงที่สำหรับระบบน้ำเหมาจ่าย (defaults)
define('WATER_BASE_UNITS', 10);     // หน่วยฐาน (เหมาจ่าย) – จะถูกแทนที่หากตั้งค่าใน system_settings
define('WATER_BASE_PRICE', 200);    // ราคาเหมาจ่าย (บาท)
define('WATER_EXCESS_RATE', 25);    // ค่าน้ำส่วนเกิน (บาท/หน่วย)

/**
 * ดึงค่าตั้งค่าจากตาราง system_settings
 */
function getSetting(string $key, $default = null) {
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val === false) return $default;
        return $val;
    } catch (Exception $e) {
        return $default;
    }
}

function getWaterBaseUnits(): int {
    $val = getSetting('water_base_units', WATER_BASE_UNITS);
    return (int)$val;
}
function getWaterBasePrice(): int {
    $val = getSetting('water_base_price', WATER_BASE_PRICE);
    return (int)$val;
}
function getWaterExcessRate(): int {
    $val = getSetting('water_excess_rate', WATER_EXCESS_RATE);
    return (int)$val;
}

/**
 * คำนวณค่าน้ำแบบเหมาจ่าย
 * 
 * @param int $units จำนวนหน่วยน้ำที่ใช้
 * @return int ค่าน้ำ (บาท)
 */
function calculateWaterCost(int $units): int {
    if ($units <= 0) return 0; // ยังไม่มีข้อมูลมิเตอร์
    $base = getWaterBaseUnits();
    $price = getWaterBasePrice();
    $excess = getWaterExcessRate();
    if ($units <= $base) return $price;
    return $price + ($units - $base) * $excess;
}

/**
 * สร้าง JavaScript function สำหรับคำนวณค่าน้ำใน frontend
 * ใช้ใน <script> tag
 * 
 * @return string JavaScript code
 */
function getWaterCalcJS(): string {
    $baseUnits = getWaterBaseUnits();
    $basePrice = getWaterBasePrice();
    $excessRate = getWaterExcessRate();
    return <<<JS
// ค่าคงที่ระบบน้ำเหมาจ่าย
var WATER_BASE_UNITS = {$baseUnits};
var WATER_BASE_PRICE = {$basePrice};
var WATER_EXCESS_RATE = {$excessRate};

function calculateWaterCost(units) {
    if (units <= 0) return 0;
    if (units <= WATER_BASE_UNITS) return WATER_BASE_PRICE;
    return WATER_BASE_PRICE + (units - WATER_BASE_UNITS) * WATER_EXCESS_RATE;
}
JS;
}
