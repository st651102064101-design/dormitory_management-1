<?php
session_start();

// Display current session
echo "<h2>Current Session Variables:</h2>";
echo "<pre>";
echo "tenant_id: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "\n";
echo "tenant_name: " . ($_SESSION['tenant_name'] ?? 'NOT SET') . "\n";
echo "tenant_email: " . ($_SESSION['tenant_email'] ?? 'NOT SET') . "\n";
echo "tenant_phone: " . ($_SESSION['tenant_phone'] ?? 'NOT SET') . "\n";
echo "tenant_logged_in: " . ($_SESSION['tenant_logged_in'] ?? 'NOT SET') . "\n";
echo "</pre>";

// For testing, set a dummy session
if (isset($_GET['test'])) {
    $_SESSION['tenant_id'] = 'T177003915821';
    $_SESSION['tenant_name'] = 'เกรียงไกร คงเมือง';
    $_SESSION['tenant_email'] = 'kriangkrai2018@gmail.com';
    $_SESSION['tenant_phone'] = '0980102587';
    $_SESSION['tenant_logged_in'] = true;
    echo "<p><strong>✓ Test session set. <a href='Public/booking_status.php?auto=1'>Go to booking status</a></strong></p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        a { color: blue; text-decoration: none; padding: 10px 20px; background: #e3f2fd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Booking Status Session Test</h1>
    
    <?php if (!isset($_SESSION['tenant_logged_in'])): ?>
        <p><strong>Session not set.</strong></p>
        <p><a href="?test=1">Click to set test session</a></p>
    <?php else: ?>
        <p><strong>Session is set:</strong></p>
        <ul>
            <li>ID: <?php echo htmlspecialchars($_SESSION['tenant_id']); ?></li>
            <li>Name: <?php echo htmlspecialchars($_SESSION['tenant_name']); ?></li>
            <li>Email: <?php echo htmlspecialchars($_SESSION['tenant_email']); ?></li>
        </ul>
        <p><a href="Public/booking_status.php?auto=1">Open Booking Status</a></p>
        <p><a href="?">Clear & Reset</a></p>
    <?php endif; ?>
</body>
</html>
