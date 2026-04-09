<?php
/**
 * Fix missing tenant_oauth for Google login customers
 * This script checks and creates missing tenant_oauth records
 */

require_once __DIR__ . '/ConnectDB.php';

try {
    $pdo = connectDB();
    
    $tenantId = 'T177001439848';
    $email = $_GET['email'] ?? '';
    
    if (empty($email)) {
        echo '<p style="color: red;">⚠️ Email address is required: ?email=customer@gmail.com</p>';
        exit;
    }
    
    echo "<h2>Fixing tenant_oauth for: $tenantId</h2>";
    
    // Check if tenant exists
    $stmt = $pdo->prepare('SELECT * FROM tenant WHERE tnt_id = ?');
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo '<p style="color: red;">❌ Tenant not found: ' . htmlspecialchars($tenantId) . '</p>';
        exit;
    }
    
    echo '<p style="color: green;">✅ Tenant found: ' . htmlspecialchars($tenant['tnt_name']) . '</p>';
    
    // Check if tenant_oauth exists
    $stmt = $pdo->prepare('SELECT * FROM tenant_oauth WHERE tnt_id = ? AND provider = "google"');
    $stmt->execute([$tenantId]);
    $oauth = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($oauth) {
        echo '<p style="color: orange;">ℹ️ tenant_oauth already exists</p>';
        echo '<pre>' . json_encode($oauth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
    } else {
        echo '<p>Creating tenant_oauth record...</p>';
        
        // For demo, create a dummy Google provider_id
        $googleId = 'google_' . md5($email);
        
        $stmt = $pdo->prepare('
            INSERT INTO tenant_oauth (tnt_id, provider, provider_id, provider_email, created_at, updated_at)
            VALUES (?, "google", ?, ?, NOW(), NOW())
        ');
        $stmt->execute([$tenantId, $googleId, $email]);
        
        echo '<p style="color: green;">✅ tenant_oauth created successfully</p>';
        echo '<pre>
tnt_id: ' . $tenantId . '
provider: google
provider_id: ' . htmlspecialchars($googleId) . '
provider_email: ' . htmlspecialchars($email) . '
        </pre>';
    }
    
    // Show bookings
    $stmt = $pdo->prepare('SELECT bkg_id, bkg_date, bkg_checkin_date, bkg_status FROM booking WHERE tnt_id = ? ORDER BY bkg_date DESC');
    $stmt->execute([$tenantId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<h3>Bookings for this tenant:</h3>';
    if (empty($bookings)) {
        echo '<p style="color: orange;">⚠️ No bookings found</p>';
    } else {
        echo '<pre>' . json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
    }
    
    echo '<h3>Next Steps:</h3>';
    echo '<ol>';
    echo '<li>Customer should log out and log in again with Google</li>';
    echo '<li>Check session data at: <a href="session_debug.php">session_debug.php</a></li>';
    echo '<li>View booking status at: <a href="Public/booking_status.php">booking_status.php</a></li>';
    echo '</ol>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
