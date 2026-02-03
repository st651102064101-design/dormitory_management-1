<?php
/**
 * Tenant Logout
 * ออกจากระบบสำหรับผู้เช่า
 */
session_start();

// ลบ session ของ tenant
unset($_SESSION['tenant_id']);
unset($_SESSION['tenant_name']);
unset($_SESSION['tenant_phone']);
unset($_SESSION['tenant_email']);
unset($_SESSION['tenant_picture']);
unset($_SESSION['tenant_logged_in']);

// Destroy entire session
session_destroy();

// Redirect ไปหน้า Login
header('Location: /dormitory_management/Login.php');
exit;
