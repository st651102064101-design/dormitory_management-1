<?php
require 'ConnectDB.php';
$pdo = connectDB();
$pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('line_login_channel_id', '1234567890') ON DUPLICATE KEY UPDATE setting_value = '1234567890'")->execute();
