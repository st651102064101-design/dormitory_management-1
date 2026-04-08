<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
print_r($pdo->query("SHOW COLUMNS FROM tenant_workflow")->fetchAll(PDO::FETCH_ASSOC));
