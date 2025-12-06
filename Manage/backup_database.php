<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['admin_username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    
    // ดึง database name จาก connection string
    $dbName = getenv('DB_NAME') ?? 'dormitory_management';
    
    // สร้าง backup ด้วย SQL dump
    $backupDir = __DIR__ . '/../backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $filename = 'backup_' . $timestamp . '.sql';
    $filepath = $backupDir . $filename;

    // ดึงข้อมูลทุก table
    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    // เริ่มสร้าง SQL dump
    $sql = "-- Database Backup Generated at " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Tables: " . implode(', ', $tables) . "\n\n";

    foreach ($tables as $table) {
        // สร้าง CREATE TABLE statement
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
        $sql .= "\n\n-- Table structure for table `$table`\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createRow[1] . ";\n";

        // ดึง data จาก table
        $dataStmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $sql .= "\n-- Dumping data for table `$table`\n";
            
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(function($val) use ($pdo) {
                    return $val === null ? 'NULL' : $pdo->quote($val);
                }, array_values($row));

                $sql .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
            }
        }
    }

    // บันทึกไฟล์
    if (file_put_contents($filepath, $sql) !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'สำรองข้อมูลสำเร็จ',
            'filename' => $filename,
            'file' => '/Dormitory_Management/backups/' . $filename
        ]);
        exit;
    } else {
        throw new Exception('ไม่สามารถบันทึกไฟล์ backup');
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    exit;
}
