<?php
session_start();
$_SESSION['admin_username'] = 'admin';
$_SESSION['admin_id'] = 1;
require 'Reports/todo_tasks.php';
