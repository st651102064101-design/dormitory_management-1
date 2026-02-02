<?php
/**
 * Save Digital Signature API
 * บันทึกลายเซ็นอิเล็กทรอนิกส์พร้อมหลักฐานประกอบ
 */

header('Content-Type: application/json; charset=utf-8');

// Include database connection
require_once __DIR__ . '/../ConnectDB.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

try {
    // Connect to database using PDO
    $pdo = connectDB();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid request data');
    }
    
    // Validate required fields
    $contractId = $input['contract_id'] ?? null;
    $signerType = $input['signer_type'] ?? 'tenant';
    $signerName = $input['signer_name'] ?? '';
    $signatureImage = $input['signature_image'] ?? null;
    $userAgent = $input['user_agent'] ?? '';
    $screenResolution = $input['screen_resolution'] ?? '';
    
    if (!$contractId) {
        throw new Exception('Contract ID is required');
    }
    
    if (!$signatureImage) {
        throw new Exception('Signature image is required');
    }
    
    // Validate signature image format
    if (!preg_match('/^data:image\/png;base64,/', $signatureImage)) {
        throw new Exception('Invalid signature image format');
    }
    
    // Get client IP address
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                 $_SERVER['HTTP_X_REAL_IP'] ?? 
                 $_SERVER['REMOTE_ADDR'] ?? 
                 'unknown';
    
    // Clean up IP if multiple proxies
    if (strpos($ipAddress, ',') !== false) {
        $ipAddress = trim(explode(',', $ipAddress)[0]);
    }
    
    // Generate unique signature ID
    $signatureId = uniqid('sig_', true);
    
    // Current timestamp
    $signedAt = date('Y-m-d H:i:s');
    
    // Create hash of the signature data for integrity verification
    $hashData = [
        'contract_id' => $contractId,
        'signer_type' => $signerType,
        'signer_name' => $signerName,
        'signed_at' => $signedAt,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent
    ];
    $signatureHash = hash('sha256', json_encode($hashData) . $signatureImage);
    
    // Decode base64 image
    $imageData = str_replace('data:image/png;base64,', '', $signatureImage);
    $imageData = str_replace(' ', '+', $imageData);
    $decodedImage = base64_decode($imageData);
    
    if (!$decodedImage) {
        throw new Exception('Failed to decode signature image');
    }
    
    // Create signatures directory if not exists
    $signaturesDir = __DIR__ . '/../Public/Assets/Signatures';
    if (!is_dir($signaturesDir)) {
        mkdir($signaturesDir, 0755, true);
    }
    
    // Save signature image file
    $filename = $signatureId . '_' . $signerType . '_' . $contractId . '.png';
    $filepath = $signaturesDir . '/' . $filename;
    
    if (!file_put_contents($filepath, $decodedImage)) {
        throw new Exception('Failed to save signature image');
    }
    
    // Check if signature_logs table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'signature_logs'");
    
    if ($tableCheck->rowCount() === 0) {
        // Create the table
        $createTable = "
            CREATE TABLE signature_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                signature_id VARCHAR(100) NOT NULL UNIQUE,
                contract_id INT NOT NULL,
                signer_type ENUM('tenant', 'owner') NOT NULL DEFAULT 'tenant',
                signer_name VARCHAR(255),
                signature_file VARCHAR(255) NOT NULL,
                signature_hash VARCHAR(64) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                screen_resolution VARCHAR(20),
                signed_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_contract (contract_id),
                INDEX idx_signed_at (signed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($createTable);
    }
    
    // Insert signature log
    $stmt = $pdo->prepare("
        INSERT INTO signature_logs 
        (signature_id, contract_id, signer_type, signer_name, signature_file, 
         signature_hash, ip_address, user_agent, screen_resolution, signed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $signatureId,
        $contractId,
        $signerType,
        $signerName,
        $filename,
        $signatureHash,
        $ipAddress,
        $userAgent,
        $screenResolution,
        $signedAt
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'ลายเซ็นถูกบันทึกเรียบร้อยแล้ว',
        'data' => [
            'signature_id' => $signatureId,
            'contract_id' => $contractId,
            'signer_type' => $signerType,
            'signed_at' => $signedAt,
            'signature_hash' => $signatureHash,
            'evidence' => [
                'ip_address' => $ipAddress,
                'user_agent' => substr($userAgent, 0, 100) . '...',
                'screen_resolution' => $screenResolution
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
