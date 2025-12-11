<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// สั้น ๆ: คืนค่า PDO connection
function connectDB(){
	$host = 'localhost:3306';
	$db   = 'dormitory_management_db';
	$user = 'root';
	$pass = '12345678';
	$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
	try {
		$pdo = new PDO($dsn, $user, $pass, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
		return $pdo;
	} catch (PDOException $e) {
		echo 'Connection failed: ' . $e->getMessage();
		exit;
	}
}

