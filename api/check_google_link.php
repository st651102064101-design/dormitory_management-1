<?php
/**
 * Check Google OAuth Link Status
 * Returns whether the current admin has a Google account linked
 */
session_start();
require_once __DIR__ . '/../ConnectDB.php';

header('Content-Type: application/json');

// ตรวจสอบว่าล็อกอินอยู่หรือไม่
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $pdo = connectDB();
    
    // ตรวจสอบว่ามี Google OAuth อยู่หรือไม่
    $stmt = $pdo->prepare("
        SELECT provider_email, picture 
        FROM admin_oauth 
        WHERE admin_id = ? AND provider = 'google'
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $oauthData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($oauthData) {
        // อัพเดท session picture ถ้ามี
        if (!empty($oauthData['picture'])) {
            $_SESSION['admin_picture'] = $oauthData['picture'];
        }
        
        echo json_encode([
            'success' => true,
            'linked' => true,
            'email' => $oauthData['provider_email'],
            'picture' => $oauthData['picture']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'linked' => false
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
