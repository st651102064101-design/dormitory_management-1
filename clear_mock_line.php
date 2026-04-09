<?php
require 'ConnectDB.php';
$pdo = connectDB();
$pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'line_login_channel_id' AND setting_value = '1234567890'")->execute();
