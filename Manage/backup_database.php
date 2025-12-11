<?php
ob_start();
session_start();
ob_clean();

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_username'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'ไม่ได้รับอนุญาต'], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'วิธีร้องขอไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE));
}

try {
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();
    
    $backupDir = __DIR__ . '/../backups/';
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0777, true)) {
            throw new Exception('ไม่สามารถสร้างโฟลเดอร์ backups');
        }
    }

    // ตรวจสอบว่าสามารถเขียนไฟล์ได้
    if (!is_writable($backupDir)) {
        throw new Exception('ไม่มีสิทธิ์เขียนไฟล์ในโฟลเดอร์ backups');
    }

    $timestamp = date('Y-m-d_H-i-s');
    $filename = 'backup_' . $timestamp . '.sql';
    $filepath = $backupDir . $filename;

    // ดึงรายการตาราทั้งหมด
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        throw new Exception('ไม่พบตารางในฐานข้อมูล');
    }

    // สร้างไฟล์ SQL dump
    $sql = "-- Backup: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $sql .= "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`;\n";
        
        $create = $pdo->query("SHOW CREATE TABLE `" . str_replace('`', '``', $table) . "`")->fetch();
        $sql .= $create['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `" . str_replace('`', '``', $table) . "`")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $cols = [];
            $vals = [];
            
            foreach ($row as $col => $val) {
                $cols[] = '`' . str_replace('`', '``', $col) . '`';
                $vals[] = ($val === null) ? 'NULL' : "'" . str_replace("'", "''", $val) . "'";
            }
            
            $sql .= "INSERT INTO `" . str_replace('`', '``', $table) . "` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    if (file_put_contents($filepath, $sql) !== false) {
        ob_end_clean();
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'สำรองข้อมูลสำเร็จ',
            'filename' => $filename,
            'file' => '/Dormitory_Management/backups/' . $filename
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        throw new Exception('ไม่สามารถเขียนไฟล์ backup');
    }

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}