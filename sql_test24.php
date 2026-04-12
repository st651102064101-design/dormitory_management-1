<?php
$_GET['completed'] = 1;
ob_start();
include 'Reports/tenant_wizard.php';
$html = ob_get_clean();
preg_match_all('/<tr data-wiz-group="1".*?<\/tr>/is', $html, $matches);
echo "Completed rows matched: " . count($matches[0]) . "\n";
if (count($matches[0]) > 0) {
    preg_match_all('/data-wiz-bkgid="([^"]+)"/', $html, $bkg_matches);
    echo "Found BKG IDs: " . implode(", ", $bkg_matches[1]) . "\n";
}
