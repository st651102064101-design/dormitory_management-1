<?php
require 'ConnectDB.php';
$pdo = connectDB();
// check what 3 represents
$stmt = $pdo->query("SELECT COUNT(*) FROM booking WHERE bkg_status NOT IN ('0', '1', '2')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
