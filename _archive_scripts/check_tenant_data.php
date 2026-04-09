<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "=== Checking Tenant T177001439848 ===\n\n";

// Check tenant
$stmt = $pdo->prepare('SELECT * FROM tenant WHERE tnt_id = ?');
$stmt->execute(['T177001439848']);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Tenant Data:\n";
if ($tenant) {
    echo json_encode($tenant, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "Not found\n";
}

// Check tenant_oauth
echo "\nTenant OAuth Data:\n";
$stmt = $pdo->prepare('SELECT * FROM tenant_oauth WHERE tnt_id = ?');
$stmt->execute(['T177001439848']);
$oauth = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($oauth, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Check bookings
echo "\nBookings for this tenant:\n";
$stmt = $pdo->prepare('SELECT bkg_id, bkg_date, bkg_checkin_date, bkg_status FROM booking WHERE tnt_id = ? ORDER BY bkg_date DESC');
$stmt->execute(['T177001439848']);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($bookings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
?>
