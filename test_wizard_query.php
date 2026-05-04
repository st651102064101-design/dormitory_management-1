<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ConnectDB.php';
require_once __DIR__ . '/includes/wizard_helper.php';

$pdo = (new ConnectDB())->getConnection();
$data = getWizardItems($pdo);
print_r($data);
