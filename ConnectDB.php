<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

if (!function_exists('connectDB')) {
    function connectDB(){
        // ข้อมูลจาก TiDB Cloud
        $host = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
        $port = '4000';
        $db   = 'dormitory_management_db';
        $user = 'utqDEKTsPzQHUm4.root';
        $pass = 'x5aHcQP0Y9m9gZMZ'; // รหัสผ่านของคุณ (ถ้าถูกต้องแล้วก็เยี่ยมเลย!)

        // DSN สำหรับ TiDB (ระบุ Port ด้วย)
        $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

        try {
            try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // แก้เป็น Path ที่ TiDB แนะนำตามในรูปที่ 4 ครับ
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
}
?>ดไฟดฟได