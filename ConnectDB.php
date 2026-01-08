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

        $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 30,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $user, $pass, $options);
            
            // Set session wait_timeout and interactive_timeout
            $pdo->exec("SET SESSION wait_timeout = 300");
            $pdo->exec("SET SESSION interactive_timeout = 300");
            
            return $pdo;

        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
            exit;
        }
    } 
} // <--- ต้องมีปีกกาปิดตัวนี้ เพื่อปิด 'if' ที่เปิดไว้ในบรรทัดที่ 5
?>