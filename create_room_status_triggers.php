<?php
/**
 * Create Triggers for Auto-Update Room Status
 * เมื่อลบ booking/contract ห้องจะถูกอัพเดทสถานะเป็น "ว่าง" อัตโนมัติ
 */

declare(strict_types=1);
require_once __DIR__ . '/ConnectDB.php';

$pdo = connectDB();

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔧 Create Triggers: Auto-Update Room Status\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$triggers = [
    // Trigger หลังเพิ่ม booking ใหม่
    [
        'name' => 'after_booking_insert',
        'drop' => "DROP TRIGGER IF EXISTS `after_booking_insert`",
        'create' => "
            CREATE TRIGGER `after_booking_insert` 
            AFTER INSERT ON `booking` 
            FOR EACH ROW 
            BEGIN
                -- เมื่อเพิ่ม booking ใหม่ (status 1=จอง หรือ 2=เข้าพักแล้ว) → ห้องไม่ว่าง
                IF NEW.bkg_status IN ('1', '2') THEN
                    UPDATE room SET room_status = '1' WHERE room_id = NEW.room_id;
                END IF;
            END
        "
    ],
    // Trigger หลังเพิ่ม contract ใหม่
    [
        'name' => 'after_contract_insert',
        'drop' => "DROP TRIGGER IF EXISTS `after_contract_insert`",
        'create' => "
            CREATE TRIGGER `after_contract_insert` 
            AFTER INSERT ON `contract` 
            FOR EACH ROW 
            BEGIN
                -- เมื่อเพิ่ม contract ใหม่ (status 0=ปกติ) → ห้องไม่ว่าง
                IF NEW.ctr_status = '0' THEN
                    UPDATE room SET room_status = '1' WHERE room_id = NEW.room_id;
                END IF;
            END
        "
    ],
    // Trigger หลังลบ booking
    [
        'name' => 'after_booking_delete',
        'drop' => "DROP TRIGGER IF EXISTS `after_booking_delete`",
        'create' => "
            CREATE TRIGGER `after_booking_delete` 
            AFTER DELETE ON `booking` 
            FOR EACH ROW 
            BEGIN
                DECLARE active_bookings INT;
                DECLARE active_contracts INT;
                
                -- นับ booking ที่ยังใช้งานอยู่ (status 1=จอง, 2=เข้าพักแล้ว)
                SELECT COUNT(*) INTO active_bookings 
                FROM booking 
                WHERE room_id = OLD.room_id AND bkg_status IN ('1', '2');
                
                -- นับ contract ที่ยังใช้งานอยู่ (status 0=ปกติ)
                SELECT COUNT(*) INTO active_contracts 
                FROM contract 
                WHERE room_id = OLD.room_id AND ctr_status = '0';
                
                -- ถ้าไม่มี booking และ contract ที่ใช้งาน → อัพเดทห้องเป็นว่าง
                IF active_bookings = 0 AND active_contracts = 0 THEN
                    UPDATE room SET room_status = '0' WHERE room_id = OLD.room_id;
                END IF;
            END
        "
    ],
    // Trigger หลังลบ contract
    [
        'name' => 'after_contract_delete',
        'drop' => "DROP TRIGGER IF EXISTS `after_contract_delete`",
        'create' => "
            CREATE TRIGGER `after_contract_delete` 
            AFTER DELETE ON `contract` 
            FOR EACH ROW 
            BEGIN
                DECLARE active_bookings INT;
                DECLARE active_contracts INT;
                
                -- นับ booking ที่ยังใช้งานอยู่
                SELECT COUNT(*) INTO active_bookings 
                FROM booking 
                WHERE room_id = OLD.room_id AND bkg_status IN ('1', '2');
                
                -- นับ contract ที่ยังใช้งานอยู่
                SELECT COUNT(*) INTO active_contracts 
                FROM contract 
                WHERE room_id = OLD.room_id AND ctr_status = '0';
                
                -- ถ้าไม่มี booking และ contract ที่ใช้งาน → อัพเดทห้องเป็นว่าง
                IF active_bookings = 0 AND active_contracts = 0 THEN
                    UPDATE room SET room_status = '0' WHERE room_id = OLD.room_id;
                END IF;
            END
        "
    ],
    // Trigger หลังอัพเดท booking status เป็นยกเลิก
    [
        'name' => 'after_booking_update',
        'drop' => "DROP TRIGGER IF EXISTS `after_booking_update`",
        'create' => "
            CREATE TRIGGER `after_booking_update` 
            AFTER UPDATE ON `booking` 
            FOR EACH ROW 
            BEGIN
                DECLARE active_bookings INT;
                DECLARE active_contracts INT;
                
                -- ถ้า status เปลี่ยนเป็นยกเลิก (0)
                IF NEW.bkg_status = '0' AND OLD.bkg_status != '0' THEN
                    SELECT COUNT(*) INTO active_bookings 
                    FROM booking 
                    WHERE room_id = NEW.room_id AND bkg_status IN ('1', '2');
                    
                    SELECT COUNT(*) INTO active_contracts 
                    FROM contract 
                    WHERE room_id = NEW.room_id AND ctr_status = '0';
                    
                    IF active_bookings = 0 AND active_contracts = 0 THEN
                        UPDATE room SET room_status = '0' WHERE room_id = NEW.room_id;
                    END IF;
                END IF;
            END
        "
    ],
    // Trigger หลังอัพเดท contract status เป็นยกเลิก
    [
        'name' => 'after_contract_update',
        'drop' => "DROP TRIGGER IF EXISTS `after_contract_update`",
        'create' => "
            CREATE TRIGGER `after_contract_update` 
            AFTER UPDATE ON `contract` 
            FOR EACH ROW 
            BEGIN
                DECLARE active_bookings INT;
                DECLARE active_contracts INT;
                
                -- ถ้า status เปลี่ยนเป็นยกเลิก (1) หรือแจ้งยกเลิก (2)
                IF NEW.ctr_status IN ('1', '2') AND OLD.ctr_status = '0' THEN
                    SELECT COUNT(*) INTO active_bookings 
                    FROM booking 
                    WHERE room_id = NEW.room_id AND bkg_status IN ('1', '2');
                    
                    SELECT COUNT(*) INTO active_contracts 
                    FROM contract 
                    WHERE room_id = NEW.room_id AND ctr_status = '0';
                    
                    IF active_bookings = 0 AND active_contracts = 0 THEN
                        UPDATE room SET room_status = '0' WHERE room_id = NEW.room_id;
                    END IF;
                END IF;
            END
        "
    ]
];

foreach ($triggers as $trigger) {
    echo "➤ Creating trigger: {$trigger['name']}... ";
    try {
        // Drop existing trigger
        $pdo->exec($trigger['drop']);
        // Create new trigger
        $pdo->exec($trigger['create']);
        echo "✅\n";
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Show all triggers
echo "\n🔍 ตรวจสอบ Triggers ทั้งหมด:\n";
$stmt = $pdo->query("SHOW TRIGGERS");
$allTriggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($allTriggers) {
    printf("%-25s %-12s %-10s\n", 'Trigger Name', 'Table', 'Event');
    echo str_repeat('-', 50) . "\n";
    foreach ($allTriggers as $t) {
        printf("%-25s %-12s %-10s\n", $t['Trigger'], $t['Table'], $t['Event']);
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Triggers สร้างเรียบร้อย!\n";
echo "\n📋 การทำงานอัตโนมัติ:\n";
echo "• เพิ่ม booking/contract → ห้องเป็น 'ไม่ว่าง'\n";
echo "• ลบ booking/contract → ห้องเป็น 'ว่าง'\n";
echo "• ยกเลิก booking/contract → ห้องเป็น 'ว่าง'\n";
echo "• (ถ้าไม่มี booking/contract อื่นที่ใช้งานอยู่)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
