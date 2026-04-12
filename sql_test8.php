<?php
require 'ConnectDB.php';
$pdo = connectDB();
require 'includes/wizard_helper.php';
$items = getWizardItems($pdo)['items'];
foreach ($items as $item) {
    if ($item['current_step'] == 5) {
        $ctr = $item['ctr_id'];
        $stmt = $pdo->query("SELECT * FROM utility WHERE ctr_id = " . (int)$ctr);
        $utils = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Room {$item['room_number']}: meters: " . count($utils) . "\n";
        print_r($utils);
    }
}
