<?php
require_once 'ConnectDB.php';
$stmt = $pdo->query("SELECT * FROM expense ORDER BY exp_id DESC LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
