<?php
require 'ConnectDB.php';
$pdo = connectDB();
$stmt = $pdo->prepare("
    SELECT c.*, 
           t.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age, t.line_user_id, t.is_weather_alert_enabled,
           r.room_id, r.room_number, r.room_image,
           rt.type_name, rt.type_price,
           tw.current_step, tw.step_3_confirmed, tw.checkin_date, tw.water_meter_start, tw.elec_meter_start
    FROM contract c
    JOIN tenant t ON c.tnt_id = t.tnt_id
    JOIN room r ON c.room_id = r.room_id
    LEFT JOIN roomtype rt ON r.type_id = rt.type_id
    LEFT JOIN tenant_workflow tw ON t.tnt_id = tw.tnt_id
    WHERE c.access_token = ? AND c.ctr_status IN ('0', '2')
    ORDER BY tw.id DESC
    LIMIT 1
");
$stmt->execute(['c7edebc56b6a3ce14af1c213233138b2']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
