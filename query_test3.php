<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("DESCRIBE tenant_workflow");
print_r($stmt->fetchAll());
