<?php
/**
 * Tenant Auth Helper - ตรวจสอบสิทธิ์การเข้าถึง
 */
declare(strict_types=1);

function checkTenantAuth(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once __DIR__ . '/../ConnectDB.php';
    $pdo = connectDB();
    
    // รับ token จาก URL หรือ session
    $token = $_GET['token'] ?? $_SESSION['tenant_token'] ?? '';
    
    if (empty($token)) {
        header('Location: ../index.php');
        exit;
    }
    
    // ตรวจสอบ token
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age,
                   r.room_id, r.room_number, r.room_image,
                   rt.type_name, rt.type_price
            FROM contract c
            JOIN tenant t ON c.tnt_id = t.tnt_id
            JOIN room r ON c.room_id = r.room_id
            LEFT JOIN roomtype rt ON r.type_id = rt.type_id
            WHERE c.access_token = ? AND c.ctr_status IN ('0', '2')
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract) {
            header('Location: ../index.php');
            exit;
        }
        
        // อัพเดท session
        $_SESSION['tenant_token'] = $token;
        $_SESSION['tenant_ctr_id'] = $contract['ctr_id'];
        $_SESSION['tenant_tnt_id'] = $contract['tnt_id'];
        $_SESSION['tenant_room_id'] = $contract['room_id'];
        $_SESSION['tenant_room_number'] = $contract['room_number'];
        $_SESSION['tenant_name'] = $contract['tnt_name'];
        
        return [
            'pdo' => $pdo,
            'token' => $token,
            'contract' => $contract
        ];
        
    } catch (PDOException $e) {
        header('Location: ../index.php');
        exit;
    }
}

function getSystemSettings(PDO $pdo): array {
    $settings = [
        'site_name' => 'Sangthian Dormitory',
        'logo_filename' => 'Logo.jpg'
    ];
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'logo_filename')");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {}
    
    return $settings;
}
