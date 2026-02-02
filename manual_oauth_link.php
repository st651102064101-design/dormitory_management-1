<?php
/**
 * Manual tenant OAuth linker
 * ใช้ลิงค์นี้เพื่อเชื่อม Google account กับ tenant ที่มีอยู่แล้ว
 */

session_start();
require_once __DIR__ . '/ConnectDB.php';

// Check if user provided tenant ID
$tenantId = $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';
$action = $_GET['action'] ?? 'link';

if (empty($tenantId)) {
    echo '
    <form method="POST">
        <h2>Link Google Account to Tenant</h2>
        <label>Tenant ID: <input type="text" name="tenant_id" required></label><br>
        <label>Email: <input type="text" name="email" required></label><br>
        <button type="submit">Link</button>
    </form>
    ';
    exit;
}

try {
    $pdo = connectDB();
    
    // Check if tenant exists
    $stmt = $pdo->prepare('SELECT * FROM tenant WHERE tnt_id = ?');
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo '<p style="color: red;">❌ Tenant not found: ' . htmlspecialchars($tenantId) . '</p>';
        exit;
    }
    
    echo '<p style="color: green;">✅ Tenant found: ' . htmlspecialchars($tenant['tnt_name']) . '</p>';
    
    // Create/Update tenant_oauth
    $googleId = 'google_' . md5($email);
    
    $stmt = $pdo->prepare('
        INSERT INTO tenant_oauth (tnt_id, provider, provider_id, provider_email, created_at, updated_at)
        VALUES (?, "google", ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            provider_id = VALUES(provider_id),
            provider_email = VALUES(provider_email),
            updated_at = NOW()
    ');
    $stmt->execute([$tenantId, $googleId, $email]);
    
    echo '<p style="color: green;">✅ OAuth link created successfully</p>';
    echo '<pre>';
    echo "Tenant ID: $tenantId\n";
    echo "Email: $email\n";
    echo "Provider ID: $googleId\n";
    echo '</pre>';
    
    // Show booking info
    $stmt = $pdo->prepare('SELECT bkg_id, bkg_date, bkg_checkin_date FROM booking WHERE tnt_id = ? AND bkg_status IN ("1","2")');
    $stmt->execute([$tenantId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<h3>Bookings:</h3>';
    if (empty($bookings)) {
        echo '<p>No active bookings</p>';
    } else {
        echo '<ul>';
        foreach ($bookings as $b) {
            echo '<li>' . htmlspecialchars($b['bkg_id']) . ' - ' . htmlspecialchars($b['bkg_checkin_date']) . '</li>';
        }
        echo '</ul>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
