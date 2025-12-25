<?php
// Redirect/forward to the real booking_status.php in the project's Public folder
// This keeps existing URLs under Reports/Public working without duplicating code.
$target = __DIR__ . '/../../Public/booking_status.php';
if (file_exists($target)) {
    require $target;
} else {
    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
    echo 'File not found.';
}
