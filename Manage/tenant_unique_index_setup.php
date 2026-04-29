<?php
/**
 * tenant_unique_index_setup.php
 *
 * ตรวจสอบและสร้าง unique index สำหรับ tenant (tnt_name, tnt_phone)
 * เพื่อป้องกันข้อมูลผู้เช่าซ้ำกันในฐานข้อมูล
 *
 * ใช้งานจาก CLI หรือเบราว์เซอร์ได้
 */

declare(strict_types=1);

require_once __DIR__ . '/../ConnectDB.php';

try {
    $pdo = connectDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $duplicatesStmt = $pdo->query(
        "SELECT tnt_name, tnt_phone, COUNT(*) AS cnt, GROUP_CONCAT(tnt_id ORDER BY tnt_id SEPARATOR ', ') AS ids
         FROM tenant
         WHERE COALESCE(tnt_name, '') != '' AND COALESCE(tnt_phone, '') != ''
         GROUP BY tnt_name, tnt_phone
         HAVING cnt > 1"
    );

    $duplicates = $duplicatesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($duplicates)) {
        echo "พบผู้เช่าซ้ำตามชื่อและเบอร์โทรศัพท์ จำนวน " . count($duplicates) . " รายการ:\n\n";
        foreach ($duplicates as $row) {
            echo sprintf("- ชื่อ: %s, เบอร์: %s, จำนวน: %s, tnt_id: %s\n", $row['tnt_name'], $row['tnt_phone'], $row['cnt'], $row['ids']);
        }
        echo "\nกรุณาลบหรือรวมข้อมูลซ้ำก่อนสร้างอินเด็กซ์ unique\n";
        exit(1);
    }

    $indexName = 'uniq_tnt_name_phone';
    $checkIndex = $pdo->prepare("SHOW INDEX FROM tenant WHERE Key_name = ?");
    $checkIndex->execute([$indexName]);
    if ($checkIndex->fetch()) {
        echo "อินเด็กซ์ unique '$indexName' มีอยู่แล้ว\n";
        exit(0);
    }

    $pdo->exec("ALTER TABLE tenant ADD UNIQUE KEY {$indexName} (tnt_name, tnt_phone)");
    echo "สร้าง unique index '{$indexName}' สำเร็จแล้ว\n";
    exit(0);
} catch (PDOException $e) {
    echo 'เกิดข้อผิดพลาด: ' . $e->getMessage() . "\n";
    exit(1);
}
