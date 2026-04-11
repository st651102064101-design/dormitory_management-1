<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM expense WHERE exp_total > 0 ORDER BY exp_id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nUtilities:\n";
$stmt2 = $pdo->query("SELECT * FROM utility ORDER BY utl_id DESC LIMIT 5");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
