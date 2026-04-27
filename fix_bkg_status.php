<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->query("
    UPDATE booking b
    JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id
    SET b.bkg_status = '1'
    WHERE tw.current_step < 5 AND b.bkg_status = '2'
");
echo "Rows affected: " . $stmt->rowCount() . "\n";
?>
