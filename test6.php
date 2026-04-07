<?php
require 'ConnectDB.php';
$pdo = connectDB();
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rate'");
    if ($stmt->rowCount() > 0) {
        $cols = $pdo->query("DESCRIBE rate")->fetchAll(PDO::FETCH_COLUMN);
        echo "Valid table rate: " . implode(", ", $cols) . "\n";
    } else {
        echo "Table rate does not exist!\n";
    }
} catch (Exception $e) { echo $e->getMessage(); }
