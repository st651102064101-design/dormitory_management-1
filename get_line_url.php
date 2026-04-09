<?php
require 'ConnectDB.php';
$pdo = connectDB();
echo $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'line_add_friend_url'")->fetchColumn();
