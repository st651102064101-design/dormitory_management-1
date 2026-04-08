<?php
require 'ConnectDB.php';
$pdo = connectDB();
echo "== tenant ==\n";
$stmt = $pdo->query("DESCRIBE tenant");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "== tenant_oauth ==\n";
$stmt = $pdo->query("DESCRIBE tenant_oauth");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
