<?php
session_start();
$_SESSION['admin_username'] = 'test';
echo "<h2>POST Data:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

if (!empty($_POST)) {
    require_once __DIR__ . '/ConnectDB.php';
    $pdo = connectDB();
    
    $ctrId = (int)$_POST['ctr_id'];
    $waterMeter = (int)$_POST['water_meter'];
    $electricMeter = (int)$_POST['electric_meter'];
    $meterDate = $_POST['meter_date'];
    
    echo "ctr_id: $ctrId, water: $waterMeter, electric: $electricMeter, date: $meterDate<br>";
    
    if ($ctrId > 0) {
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date)
                VALUES (?, 0, ?, 0, ?, ?)
            ");
            $insertStmt->execute([$ctrId, $waterMeter, $electricMeter, $meterDate]);
            echo "SUCCESS! Inserted ID: " . $pdo->lastInsertId();
        } catch (PDOException $e) {
            echo "ERROR: " . $e->getMessage();
        }
    } else {
        echo "ERROR: ctr_id is 0 or empty!";
    }
}
?>
<form method="post">
    <input type="hidden" name="save_meter" value="1">
    <input type="hidden" name="ctr_id" value="15">
    <input type="hidden" name="room_id" value="4">
    <input type="hidden" name="room_number" value="4">
    <input type="hidden" name="meter_date" value="2025-12-11">
    Water: <input type="number" name="water_meter" value="100"><br>
    Electric: <input type="number" name="electric_meter" value="200"><br>
    <button type="submit">Test Submit</button>
</form>
