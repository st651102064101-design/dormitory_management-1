<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require 'ConnectDB.php';
require 'includes/wizard_helper.php';
$pdo = connectDB();
$data = getWizardItems($pdo);
echo "Count from getWizardItems: " . count($data['items']) . "\n";
foreach($data['items'] as $w) {
    echo "Room: " . $w['room_number'] . " Tenant: " . $w['tnt_name'] . "\n";
}
