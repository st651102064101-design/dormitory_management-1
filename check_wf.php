<?php
require_once 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("SELECT * FROM tenant_workflow w JOIN contract c ON w.tnt_id = c.tnt_id JOIN room r ON c.room_id = r.room_id WHERE r.room_number = '1' ORDER BY w.id DESC LIMIT 1");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
