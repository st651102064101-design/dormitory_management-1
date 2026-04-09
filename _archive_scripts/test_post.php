<?php
session_start();
$_SESSION['admin_username'] = 'test';
require_once __DIR__ . '/ConnectDB.php';
$pdo = connectDB();

echo "<h2>POST Data:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

if (!empty($_POST['ctr_id'])) {
    $ctrId = (int)$_POST['ctr_id'];
    $waterMeter = (int)$_POST['water_meter'];
    $electricMeter = (int)$_POST['electric_meter'];
    $meterDate = $_POST['meter_date'];
    
    echo "Processing: ctr_id=$ctrId, water=$waterMeter, electric=$electricMeter, date=$meterDate<br>";
    
    try {
        // Check existing
        $checkStmt = $pdo->prepare("SELECT utl_id FROM utility WHERE ctr_id = ? AND MONTH(utl_date) = MONTH(?) AND YEAR(utl_date) = YEAR(?)");
        $checkStmt->execute([$ctrId, $meterDate, $meterDate]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            echo "Record exists for this month: utl_id=" . $existing['utl_id'] . "<br>";
        } else {
            echo "No record for this month, inserting...<br>";
            $insertStmt = $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) VALUES (?, 0, ?, 0, ?, ?)");
            $insertStmt->execute([$ctrId, $waterMeter, $electricMeter, $meterDate]);
            echo "SUCCESS! Inserted ID: " . $pdo->lastInsertId() . "<br>";
        }
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "<br>";
    }
}

// Show all utility records
echo "<h2>All Utility Records:</h2>";
$stmt = $pdo->query("SELECT u.*, r.room_number FROM utility u JOIN contract c ON u.ctr_id = c.ctr_id JOIN room r ON c.room_id = r.room_id ORDER BY u.utl_id DESC");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
