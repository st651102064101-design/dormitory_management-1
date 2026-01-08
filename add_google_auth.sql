-- เพิ่มคอลัมน์สำหรับ Google Authentication ในตาราง admin
ALTER TABLE `admin` 
ADD COLUMN `admin_email` VARCHAR(255) NULL COMMENT 'อีเมล' AFTER `admin_line`,
ADD COLUMN `google_id` VARCHAR(255) NULL COMMENT 'Google ID' AFTER `admin_email`;

-- เพิ่มการตั้งค่า Google OAuth ใน system_settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES 
('google_client_id', ''),
('google_client_secret', ''),
('google_redirect_uri', '/dormitory_management/google_callback.php')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
