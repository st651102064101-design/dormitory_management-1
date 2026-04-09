<?php
/**
 * Manage Rates - เปลี่ยนเป็นแสดงหน้าการตั้งค่า Apple Sheet
 */

session_start();
if (empty($_SESSION['admin_username'])) {
    header("Location: ../Login.php");
    exit;
}

// Redirect ไปเปิดหน้าจัดการอัตราค่าน้ำค่าไฟ (แบบ Apple Sheet) ภายในหน้าตั้งค่าหลัก
header("Location: system_settings.php#sheet-rates");
exit;
