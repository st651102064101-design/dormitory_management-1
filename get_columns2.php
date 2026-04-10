<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SHOW COLUMNS FROM tenant_workflow");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
