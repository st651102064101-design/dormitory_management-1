<?php
session_start();
$_SESSION['admin_username'] = 'admin01';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2 style='color:green'>POST Received!</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (isset($_POST['save_meter'])) {
        require_once __DIR__ . '/../ConnectDB.php';
        $pdo = connectDB();
        
        $ctrId = (int)$_POST['ctr_id'];
        $water = (int)$_POST['water_meter'];
        $electric = (int)$_POST['electric_meter'];
        $date = $_POST['meter_date'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) VALUES (?, 0, ?, 0, ?, ?)");
            $stmt->execute([$ctrId, $water, $electric, $date]);
            echo "<h3 style='color:lime'>Inserted! ID: " . $pdo->lastInsertId() . "</h3>";
        } catch (PDOException $e) {
            echo "<h3 style='color:red'>Error: " . $e->getMessage() . "</h3>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Test Form</title></head>
<body style="background:#1a1a2e;color:white;padding:2rem;">
<h1>Test Form Submission</h1>
<form method="post" action="">
    <input type="hidden" name="save_meter" value="1">
    <input type="hidden" name="ctr_id" value="16">
    <input type="hidden" name="room_id" value="5">
    <input type="hidden" name="room_number" value="5">
    <input type="hidden" name="meter_date" value="2025-12-11">
    
    <p>Water: <input type="number" name="water_meter" value="50"></p>
    <p>Electric: <input type="number" name="electric_meter" value="100"></p>
    <p><button type="submit" style="padding:1rem 2rem;font-size:1.2rem;background:blue;color:white;border:none;cursor:pointer;">Submit Test</button></p>
</form>
</body>
</html>
