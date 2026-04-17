<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM utility WHERE ctr_id IN (SELECT ctr_id FROM contract WHERE room_id = 2) AND utl_date >= '2026-04-01'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
