<?php
require 'ConnectDB.php';
$pdo = connectDB();
require 'includes/wizard_helper.php';
$items = getWizardItems($pdo)['items'];
foreach ($items as $item) {
    if ($item['current_step'] == 5) {
        // check if paid
        $bkg = $item['bkg_id'];
        $ctr = $item['ctr_id'];
        $stmt = $pdo->prepare("SELECT e.exp_total, (SELECT SUM(pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND pay_status='1') as paid FROM expense e WHERE e.ctr_id = ?");
        $stmt->execute([$ctr]);
        $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "BKG $bkg (Room {$item['room_number']}): \n";
        print_r($bills);
    }
}
