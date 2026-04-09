<?php
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("DESCRIBE expense");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
