<?php
// 1. ดึงไฟล์เชื่อมต่อมาใช้งาน
require_once 'ConnectDB.php';

try {
    // 2. เรียกใช้ฟังก์ชันเชื่อมต่อ
    $pdo = connectDB();

    if ($pdo) {
        echo "✅ เชื่อมต่อฐานข้อมูล TiDB สำเร็จ!";
        
        // 3. ลองดึงชื่อตารางที่มีอยู่ใน DB ออกมาโชว์
        $query = $pdo->query("SHOW TABLES");
        $tables = $query->fetchAll(PDO::FETCH_COLUMN);

        echo "<h3>รายชื่อตารางในฐานข้อมูลของคุณ:</h3>";
        if (empty($tables)) {
            echo "เชื่อมต่อได้แล้ว แต่ยังไม่มีตารางในฐานข้อมูลครับ";
        } else {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";
        }
    }
} catch (Exception $e) {
    echo "❌ การทดสอบล้มเหลว: " . $e->getMessage();
}
?>