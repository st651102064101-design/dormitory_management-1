<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Session & Database Debug</h1>
        
        <h2>Current Session Data:</h2>
        <pre><?php print_r($_SESSION); ?></pre>
        
        <?php
        require_once __DIR__ . '/ConnectDB.php';
        try {
            $pdo = connectDB();
            
            $tenantId = $_SESSION['tenant_id'] ?? null;
            $email = $_SESSION['tenant_email'] ?? null;
            
            if (!$tenantId && !$email) {
                echo '<p class="error">❌ No session data found (tenant_id or tenant_email)</p>';
            } else {
                echo '<h2>Database Check:</h2>';
                
                // Check tenant_oauth
                if ($email) {
                    echo '<h3>Searching tenant_oauth by email: ' . htmlspecialchars($email) . '</h3>';
                    $stmt = $pdo->prepare('SELECT * FROM tenant_oauth WHERE provider = "google" AND provider_email = ?');
                    $stmt->execute([$email]);
                    $oauth = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo '<pre>' . json_encode($oauth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                }
                
                // Check tenant
                if ($tenantId) {
                    echo '<h3>Tenant Info for: ' . htmlspecialchars($tenantId) . '</h3>';
                    $stmt = $pdo->prepare('SELECT tnt_id, tnt_name, tnt_phone FROM tenant WHERE tnt_id = ?');
                    $stmt->execute([$tenantId]);
                    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo '<pre>' . json_encode($tenant, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                    
                    echo '<h3>Bookings for: ' . htmlspecialchars($tenantId) . '</h3>';
                    $stmt = $pdo->prepare('SELECT bkg_id, bkg_date, bkg_checkin_date, bkg_status FROM booking WHERE tnt_id = ? ORDER BY bkg_date DESC');
                    $stmt->execute([$tenantId]);
                    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo '<pre>' . json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                    
                    if (empty($bookings)) {
                        echo '<p class="error">❌ No bookings found</p>';
                    } else {
                        echo '<p class="success">✅ Found ' . count($bookings) . ' booking(s)</p>';
                    }
                }
            }
        } catch (Exception $e) {
            echo '<p class="error">Database Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
        
        <h2>Next Steps:</h2>
        <ul>
            <li><a href="index.php">Back to Home</a></li>
            <li><a href="Public/booking_status.php">Check Booking Status</a></li>
            <li><a href="Login.php">Login Again</a></li>
        </ul>
    </div>
</body>
</html>
