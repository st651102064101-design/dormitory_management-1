<?php
$f = 'Public/booking_status.php';
$c = file_get_contents($f);
$c = str_replace(
    'WHERE (b.tnt_id = ? OR t.tnt_phone = ?) AND b.bkg_status IN (\'1\',\'2\')',
    'WHERE b.tnt_id = ? AND b.bkg_status IN (\'1\',\'2\')',
    $c
);
$c = str_replace(
    '$stmtBookings->execute([$tenantId, $tenantPhone]);',
    '$stmtBookings->execute([$tenantId]);',
    $c
);
file_put_contents($f, $c);
