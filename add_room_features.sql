-- เพิ่มค่า room_features สำหรับแสดงสิ่งอำนวยความสะดวกในการ์ดห้องพัก
-- รันใน phpMyAdmin หรือ MySQL CLI

INSERT INTO system_settings (setting_key, setting_value, updated_at) 
VALUES ('room_features', 'ไฟฟ้า,น้ำประปา,WiFi,แอร์,เฟอร์นิเจอร์', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

-- ดูผลลัพธ์
SELECT * FROM system_settings WHERE setting_key = 'room_features';
