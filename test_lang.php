<?php
// mock session
$_SESSION['admin_username'] = 'admin';
$_SESSION['system_language'] = 'en';

require_once __DIR__ . '/includes/lang.php';
echo "Language loaded successfully. Welcome: " . __('dashboard_title');
