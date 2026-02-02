<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

// Check tenant table structure
$stmt = $pdo->query("DESCRIBE tenant");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Tenant Table Columns:</h2>\n";
echo "<pre>" . json_encode($columns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";

// Show first few tenant records
$stmt = $pdo->query("SELECT tnt_id, tnt_name FROM tenant LIMIT 5");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h2>Sample Tenants:</h2>\n";
echo "<pre>" . json_encode($tenants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
?>
