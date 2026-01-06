<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

if (!function_exists('connectDB')) {
    function connectDB(){
        $host = 'localhost';
        $port = '3306';
        $db   = 'dormitory_management_db';
        $user = 'root';
        $pass = ''; 

        $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;

        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
            exit;
        }
    } 
} // <--- ต้องมีปีกกาปิดตัวนี้ เพื่อปิด 'if' ที่เปิดไว้ในบรรทัดที่ 5
?>