<?php
session_start();
$_SESSION['admin_username'] = 'admin01';

// Get the HTML from manage_utility.php
ob_start();
$_GET['month'] = '12';
$_GET['year'] = '2025';
$_GET['show'] = 'occupied';
include 'manage_utility.php';
$html = ob_get_clean();

// Find forms for room 5
preg_match_all('/<form[^>]*class="meter-form"[^>]*>.*?<\/form>/s', $html, $matches);

echo "<h2>Found " . count($matches[0]) . " forms</h2>";

foreach ($matches[0] as $i => $form) {
    if (strpos($form, 'ห้อง 5') !== false || strpos($form, 'room_number" value="5"') !== false) {
        echo "<h3>Form for Room 5:</h3>";
        echo "<pre>" . htmlspecialchars($form) . "</pre>";
    }
}

// Also check all hidden inputs
preg_match_all('/<input[^>]*name="ctr_id"[^>]*>/s', $html, $ctrMatches);
echo "<h3>All ctr_id inputs:</h3><pre>";
print_r($ctrMatches[0]);
echo "</pre>";
