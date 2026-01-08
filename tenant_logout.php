<?php
/**
 * Tenant Logout
 * ออกจากระบบสำหรับผู้เช่า
 */
session_start();

// ลบ session ของ tenant
unset($_SESSION['tenant_id']);
unset($_SESSION['tenant_name']);
unset($_SESSION['tenant_picture']);
unset($_SESSION['tenant_logged_in']);

// Redirect กลับไปหน้าหลัก
header('Location: Login.php');
exit;
