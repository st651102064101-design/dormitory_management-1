<?php
$code = file_get_contents('Tenant/index.php');

$code = str_replace(
    't.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age,',
    't.tnt_id, t.tnt_name, t.tnt_phone, t.tnt_address, t.tnt_education, t.tnt_faculty, t.tnt_year, t.tnt_vehicle, t.tnt_parent, t.tnt_parentsphone, t.tnt_age, t.line_user_id, t.is_weather_alert_enabled,',
    $code
);

file_put_contents('Tenant/index.php', $code);
echo "Done";
?>
