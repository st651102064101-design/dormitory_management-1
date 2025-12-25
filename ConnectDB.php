<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

if (!function_exists('connectDB')) {
    function connectDB(){
        $host = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
        $port = '4000';
        $db   = 'dormitory_management_db';
        $user = 'utqDEKTsPzQHUm4.root';
        $pass = 'x5aHcQP0Y9m9gZMZ'; 

        $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // ใช้ Path ตามที่หน้าจอ TiDB แนะนำ (รูปที่ 4)
                PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/cert.pem',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
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