<?php
/**
 * Migration Script: เพิ่มคอลัมน์ picture ใน OAuth tables
 * 
 * รันไฟล์นี้เพื่อเพิ่มคอลัมน์ picture สำหรับเก็บ Google avatar URL
 * ในตาราง admin_oauth และ tenant_oauth
 */

require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Migration: เพิ่มคอลัมน์ picture ใน OAuth tables</h2>";
    echo "<pre>";
    
    // ตรวจสอบว่าคอลัมน์ picture มีอยู่แล้วหรือไม่ใน admin_oauth
    $stmt = $pdo->query("SHOW COLUMNS FROM admin_oauth LIKE 'picture'");
    if ($stmt->rowCount() == 0) {
        echo "กำลังเพิ่มคอลัมน์ picture ใน admin_oauth...\n";
        $pdo->exec("ALTER TABLE admin_oauth ADD COLUMN picture VARCHAR(500) DEFAULT NULL COMMENT 'URL รูปโปรไฟล์จาก provider' AFTER provider_email");
        echo "✓ เพิ่มคอลัมน์ picture ใน admin_oauth สำเร็จ\n\n";
    } else {
        echo "✓ คอลัมน์ picture มีอยู่แล้วใน admin_oauth\n\n";
    }
    
    // ตรวจสอบว่าคอลัมน์ picture มีอยู่แล้วหรือไม่ใน tenant_oauth
    $stmt = $pdo->query("SHOW COLUMNS FROM tenant_oauth LIKE 'picture'");
    if ($stmt->rowCount() == 0) {
        echo "กำลังเพิ่มคอลัมน์ picture ใน tenant_oauth...\n";
        $pdo->exec("ALTER TABLE tenant_oauth ADD COLUMN picture VARCHAR(500) DEFAULT NULL COMMENT 'URL รูปโปรไฟล์จาก provider' AFTER provider_email");
        echo "✓ เพิ่มคอลัมน์ picture ใน tenant_oauth สำเร็จ\n\n";
    } else {
        echo "✓ คอลัมน์ picture มีอยู่แล้วใน tenant_oauth\n\n";
    }
    
    echo "</pre>";
    echo "<h3 style='color: green;'>✓ Migration เสร็จสมบูรณ์</h3>";
    echo "<p><a href='index.php'>กลับหน้าหลัก</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>เกิดข้อผิดพลาด:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}
