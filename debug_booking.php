<?php
session_start();
require_once __DIR__ . '/ConnectDB.php';

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Debug Booking Status</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 300px; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f9f9f9; }
        .success { border-left-color: #28a745; }
        .error { border-left-color: #dc3545; }
        .warning { border-left-color: #ffc107; }
        button { padding: 10px 20px; margin: 10px 0; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Booking Status</h1>
        
        <div class="section">
            <h2>Current Session</h2>
            <pre><?php print_r($_SESSION); ?></pre>
        </div>
        
        <?php if (empty($_SESSION['tenant_id'])): ?>
            <div class="section error">
                <h2>❌ Problem: No tenant_id in session</h2>
                <p>Please login with Google first:</p>
                <a href="../google_login.php" style="padding: 10px 20px; background: #4285F4; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">Login with Google</a>
            </div>
        <?php else: ?>
            <?php
            try {
                $pdo = connectDB();
                $tenantId = $_SESSION['tenant_id'];
                
                // Check tenant exists
                $stmt = $pdo->prepare('SELECT * FROM tenant WHERE tnt_id = ?');
                $stmt->execute([$tenantId]);
                $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            
            <div class="section <?php echo $tenant ? 'success' : 'error'; ?>">
                <h2>Tenant Info</h2>
                <?php if ($tenant): ?>
                    <p>✅ Tenant found</p>
                    <pre><?php print_r($tenant); ?></pre>
                <?php else: ?>
                    <p>❌ Tenant not found: <?php echo htmlspecialchars($tenantId); ?></p>
                <?php endif; ?>
            </div>
            
            <?php
            // Check OAuth
            $stmt = $pdo->prepare('SELECT * FROM tenant_oauth WHERE tnt_id = ? AND provider = "google"');
            $stmt->execute([$tenantId]);
            $oauth = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            
            <div class="section <?php echo $oauth ? 'success' : 'warning'; ?>">
                <h2>OAuth Record</h2>
                <?php if ($oauth): ?>
                    <p>✅ OAuth record found</p>
                    <pre><?php print_r($oauth); ?></pre>
                <?php else: ?>
                    <p>⚠️ No OAuth record</p>
                    <p>Using manual linker: </p>
                    <form action="../manual_oauth_link.php" method="POST">
                        <input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars($tenantId); ?>">
                        <input type="text" name="email" placeholder="Email" required>
                        <button type="submit">Create OAuth Link</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php
            // Check bookings
            $stmt = $pdo->prepare('SELECT * FROM booking WHERE tnt_id = ? ORDER BY bkg_date DESC');
            $stmt->execute([$tenantId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <div class="section <?php echo !empty($bookings) ? 'success' : 'warning'; ?>">
                <h2>Bookings (<?php echo count($bookings); ?>)</h2>
                <?php if (empty($bookings)): ?>
                    <p>⚠️ No bookings found</p>
                <?php else: ?>
                    <p>✅ Found <?php echo count($bookings); ?> booking(s)</p>
                    <pre><?php print_r($bookings); ?></pre>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2>Next Steps</h2>
                <ol>
                    <li><a href="../Public/booking_status.php" style="color: #007bff;">Go to Booking Status Page</a></li>
                    <li>Check browser console for errors (F12)</li>
                    <li>Check PHP error log: <code>/Applications/XAMPP/xamppfiles/logs/php_error.log</code></li>
                </ol>
            </div>
            
            <?php
            } catch (Exception $e) {
                echo '<div class="section error"><p>Error: ' . htmlspecialchars($e->getMessage()) . '</p></div>';
            }
            ?>
        <?php endif; ?>
        
        <div class="section" style="margin-top: 30px; text-align: center;">
            <button onclick="location.href='../index.php'">Back to Home</button>
            <button onclick="location.href='../Login.php'">Login Page</button>
            <button onclick="location.reload()">Refresh</button>
        </div>
    </div>
</body>
</html>
