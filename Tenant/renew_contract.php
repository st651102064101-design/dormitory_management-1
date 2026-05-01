<?php
require_once __DIR__ . '/../ConnectDB.php';
session_start();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ต่อสัญญาเช่า</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background: #f8fafc; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; text-align: center; }
        .card { background: white; padding: 40px 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 400px; width: 100%; }
        .icon { font-size: 48px; margin-bottom: 20px; }
        h1 { color: #1e293b; font-size: 20px; margin-bottom: 10px; }
        p { color: #64748b; font-size: 14px; margin-bottom: 30px; }
        .btn { display: inline-block; background: #3b82f6; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚧</div>
        <h1>ระบบต่อสัญญาเช่า</h1>
        <p>ฟังก์ชันการต่อสัญญาออนไลน์กำลังอยู่ในระหว่างการพัฒนา กรุณาติดต่อชำระเงินและต่อสัญญากับเจ้าหน้าที่ดูแลหอพักโดยตรง</p>
        <a href="javascript:history.back()" class="btn">กลับหน้าหลัก</a>
    </div>
</body>
</html>
