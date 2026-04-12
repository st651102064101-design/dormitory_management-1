<?php
require 'ConnectDB.php';
$pdo = connectDB();
require 'includes/wizard_helper.php';
$items = getWizardItems($pdo)['items'];
$rooms = [];
foreach ($items as $item) {
    if ($item['current_step'] == 5) {
        $rooms[] = $item['room_number'];
    }
}
echo "Rooms still in step 5: " . implode(", ", $rooms) . "\n";
