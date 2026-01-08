-- เพิ่มคอลัมน์ picture สำหรับเก็บ URL ของรูปโปรไฟล์จาก OAuth providers

-- เพิ่มคอลัมน์ picture ใน admin_oauth
ALTER TABLE admin_oauth 
ADD COLUMN picture VARCHAR(500) DEFAULT NULL COMMENT 'URL รูปโปรไฟล์จาก provider' AFTER provider_email;

-- เพิ่มคอลัมน์ picture ใน tenant_oauth
ALTER TABLE tenant_oauth 
ADD COLUMN picture VARCHAR(500) DEFAULT NULL COMMENT 'URL รูปโปรไฟล์จาก provider' AFTER provider_email;
