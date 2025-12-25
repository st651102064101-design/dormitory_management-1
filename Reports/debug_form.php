<?php
session_start();
$_SESSION['admin_username'] = 'admin01';
require_once __DIR__ . '/../ConnectDB.php';
$pdo = connectDB();

// Get room 5 data
$stmt = $pdo->query("
    SELECT r.room_id, r.room_number, c.ctr_id, t.tnt_name
    FROM room r
    JOIN contract c ON r.room_id = c.room_id AND c.ctr_status IN ('0','1','2')
    JOIN tenant t ON c.tnt_id = t.tnt_id
    WHERE r.room_number = '5'
");
$room = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<h2>Room 5 Data:</h2>";
echo "<pre>";
print_r($room);
echo "</pre>";

if ($room) {
    $ctrId = $room['ctr_id'];
    echo "<h2>Test Form for Room 5 (ctr_id=$ctrId):</h2>";
?>
<form method="post" action="" style="background:#333;padding:1rem;">
    <input type="hidden" name="save_meter" value="1">
    <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
    <input type="hidden" name="ctr_id" value="<?php echo $ctrId; ?>">
    <input type="hidden" name="room_number" value="<?php echo $room['room_number']; ?>">
    <input type="hidden" name="meter_date" value="2025-12-11">
    <p>Water: <input type="number" name="water_meter" value="55" required></p>
    <p>Electric: <input type="number" name="electric_meter" value="110" required></p>
    <button type="submit" style="padding:1rem;background:blue;color:white;">Save Room 5</button>
</form>
<?php
}

if ($_POST) {
    echo "<h2>POST received:</h2><pre>";
    print_r($_POST);
    echo "</pre>";
    
    if (!empty($_POST['ctr_id'])) {
        $ctrId = (int)$_POST['ctr_id'];
        $water = (int)$_POST['water_meter'];
        $electric = (int)$_POST['electric_meter'];
        
        $stmt = $pdo->prepare("INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date) VALUES (?, 0, ?, 0, ?, '2025-12-11')");
        $stmt->execute([$ctrId, $water, $electric]);
        echo "<h3 style='color:lime'>Saved! ID: " . $pdo->lastInsertId() . "</h3>";
    }
}
