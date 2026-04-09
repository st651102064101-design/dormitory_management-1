<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("SELECT * FROM expense WHERE exp_id = 775775590");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
