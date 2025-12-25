<?php
// ตั้ง timezone เป็นประเทศไทย
date_default_timezone_set('Asia/Bangkok');

if (!function_exists('connectDB')) {
    function connectDB(){
        // ข้อมูลจาก TiDB Cloud
        $host = 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com';
        $port = '4000';
        $db   = 'dormitory_management_db';
        $user = 'utqDEKTsPzQHHm4.root';
        $pass = 'UTuBB1K0XsmeNjyx'; // <--- **อย่าลืมแก้รหัสผ่านตรงนี้**

        // DSN สำหรับ TiDB (ระบุ Port ด้วย)
        $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

        try {
            // เพิ่ม Option สำหรับ SSL (TiDB บังคับใช้)
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // ตั้งค่า SSL ให้รองรับ TiDB Cloud
                PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/cert.pem', // Path มาตรฐานของ CA ใน Linux/Vercel
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // ป้องกัน Error เรื่อง Certificate
            ];

            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;

        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
            exit;
        }
    }
}
?>