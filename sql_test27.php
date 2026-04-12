<?php
require 'ConnectDB.php';
$pdo = connectDB();
echo "booking:\n";
print_r($pdo->query("SELECT bkg_status, count(*) FROM booking GROUP BY bkg_status")->fetchAll());
echo "contract:\n";
print_r($pdo->query("SELECT ctr_status, count(*) FROM contract GROUP BY ctr_status")->fetchAll());
echo "tenant:\n";
print_r($pdo->query("SELECT tnt_status, count(*) FROM tenant GROUP BY tnt_status")->fetchAll());
