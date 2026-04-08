<?php
require 'ConnectDB.php';
require 'LineHelper.php';
$pdo = connectDB();
var_dump(sendLineBroadcast($pdo, "Test from shell"));
