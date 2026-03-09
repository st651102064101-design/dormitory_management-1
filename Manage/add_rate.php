<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
// debug entry
error_log('[add_rate] invoked, method=' . $_SERVER['REQUEST_METHOD']);

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
    // bring in water constants/functions for defaults
    require_once __DIR__ . '/../includes/water_calc.php';
    $pdo = connectDB();

    // กรณีใช้อัตราเดิม (คัดลอกมาสร้างใหม่พร้อมวันที่ปัจจุบัน)
    if (isset($_POST['use_rate_id'])) {
        $useRateId = (int)$_POST['use_rate_id'];
        
        // ดึงข้อมูลอัตราเดิมรวมค่าพื้นฐาน
        $stmt = $pdo->prepare('SELECT rate_water, rate_elec, water_base_units, water_base_price, water_excess_rate FROM rate WHERE rate_id = ?');
        $stmt->execute([$useRateId]);
        $oldRate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldRate) {
            echo json_encode(['success' => false, 'message' => 'ไม่พบอัตราที่ต้องการ']);
            exit;
        }
        
        // สร้างอัตราใหม่ด้วยค่าเดิมแต่วันที่ปัจจุบัน
        $stmt = $pdo->prepare('INSERT INTO rate (rate_water, rate_elec, effective_date, water_base_units, water_base_price, water_excess_rate) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $oldRate['rate_water'],
            $oldRate['rate_elec'],
            date('Y-m-d'),
            $oldRate['water_base_units'] !== null ? (int)$oldRate['water_base_units'] : WATER_BASE_UNITS,
            $oldRate['water_base_price'] !== null ? (int)$oldRate['water_base_price'] : WATER_BASE_PRICE,
            $oldRate['water_excess_rate'] !== null ? (int)$oldRate['water_excess_rate'] : WATER_EXCESS_RATE
        ]);
        $rateId = (int)$pdo->lastInsertId();
        // ensure base price setting reflects this rate
        $stmt3 = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('water_base_price', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt3->execute([(int)$oldRate['rate_water']]);

        // also return current water-base settings (may have been overridden by copy)
        $stmt2 = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('water_base_units','water_base_price','water_excess_rate')");
        $stmt2->execute();
        $settings = [];
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = (int)$row['setting_value'];
        }
        echo json_encode([
            'success' => true,
            'rate_id' => $rateId,
            'rate_water' => (int)$oldRate['rate_water'],
            'rate_elec' => (int)$oldRate['rate_elec'],
            'effective_date' => date('Y-m-d'),
            // return the values that were actually stored (copied from the old rate)
            'water_base_units' => $oldRate['water_base_units'] !== null ? (int)$oldRate['water_base_units'] : WATER_BASE_UNITS,
            'water_base_price' => $oldRate['water_base_price'] !== null ? (int)$oldRate['water_base_price'] : WATER_BASE_PRICE,
            'water_excess_rate' => $oldRate['water_excess_rate'] !== null ? (int)$oldRate['water_excess_rate'] : WATER_EXCESS_RATE,
            'message' => 'เปลี่ยนไปใช้อัตราที่เลือกสำเร็จ'
        ]);
        exit;
    }

    // กรณีเพิ่มอัตราใหม่
    $rate_water = isset($_POST['rate_water']) ? (int)$_POST['rate_water'] : null;
    $rate_elec = isset($_POST['rate_elec']) ? (int)$_POST['rate_elec'] : null;
    $effective_date = isset($_POST['effective_date']) ? trim($_POST['effective_date']) : date('Y-m-d');

    // normalize Thai-style date if provided
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $effective_date, $m)) {
        $effective_date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // fallback to today if the format is not ISO date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date)) {
        $effective_date = date('Y-m-d');
    }

    if ($rate_water === null || $rate_elec === null) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }
    // debug POST contents
    error_log('[add_rate] POST=' . json_encode($_POST));

    if ($rate_water < 0 || $rate_elec < 0) {
        echo json_encode(['success' => false, 'message' => 'อัตราต้องไม่ติดลบ']);
        exit;
    }

    // update optional water base units & excess rate settings
    // always update base price to whatever was entered as rate_water
    $basePrice = $rate_water;
    if ($basePrice < 0) $basePrice = 0;
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('water_base_price', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
    $stmt->execute([$basePrice]);

    // normalize and store the units/excess if provided
    if (isset($_POST['water_base_units'])) {
        $units = (int)$_POST['water_base_units'];
        if ($units < 0) $units = 0;
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('water_base_units', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt->execute([$units]);
        $updatedUnits = $units;
    }
    if (isset($_POST['water_excess_rate'])) {
        $rate = (int)$_POST['water_excess_rate'];
        if ($rate < 0) $rate = 0;
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES ('water_excess_rate', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt->execute([$rate]);
        $updatedExcess = $rate;
    }

    // if we didn't get explicit values from POST, pull the current settings
    if (!isset($updatedUnits)) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='water_base_units' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        $updatedUnits = $val !== false ? (int)$val : WATER_BASE_UNITS;
    }
    if (!isset($updatedExcess)) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='water_excess_rate' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        $updatedExcess = $val !== false ? (int)$val : WATER_EXCESS_RATE;
    }

    // สร้าง record ใหม่เสมอ (เก็บประวัติทุกครั้ง)
    // also store configured base units/price/excess in rate history
    $stmt = $pdo->prepare('INSERT INTO rate (rate_water, rate_elec, effective_date, water_base_units, water_base_price, water_excess_rate) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$rate_water, $rate_elec, $effective_date, $updatedUnits, $basePrice, $updatedExcess]);
    $rateId = (int)$pdo->lastInsertId();

    // fetch updated settings values to include in response
    if (!isset($updatedUnits)) {
        if (isset($units)) {
            $updatedUnits = $units;
        } else {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='water_base_units' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            $updatedUnits = $val !== false ? (int)$val : WATER_BASE_UNITS;
        }
    }
    if (!isset($updatedExcess)) {
        if (isset($rate)) {
            $updatedExcess = $rate;
        } else {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key='water_excess_rate' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            $updatedExcess = $val !== false ? (int)$val : WATER_EXCESS_RATE;
        }
    }

    // log what we're about to return
    error_log('[add_rate] returning units=' . (int)$updatedUnits . ' price=' . (int)$basePrice . ' excess=' . (int)$updatedExcess);
    echo json_encode([
        'success' => true,
        'rate_id' => $rateId,
        'rate_water' => $rate_water,
        'rate_elec' => $rate_elec,
        'effective_date' => $effective_date,
        'water_base_units' => (int)$updatedUnits,
        'water_base_price' => (int)$basePrice,
        'water_excess_rate' => (int)$updatedExcess
    ]);
} catch (Throwable $e) {
    // log full error for debugging
    error_log('[add_rate] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
