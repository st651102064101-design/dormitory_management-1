-- สร้างตารางสำหรับเก็บข้อมูล OAuth แยกจากตาราง admin
-- เพื่อหลีกเลี่ยงค่า NULL และรองรับ OAuth providers หลายตัว

CREATE TABLE IF NOT EXISTS admin_oauth (
  oauth_id INT PRIMARY KEY AUTO_INCREMENT,
  admin_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL COMMENT 'google, facebook, line',
  provider_id VARCHAR(255) NOT NULL COMMENT 'ID จาก provider',
  provider_email VARCHAR(255) NOT NULL COMMENT 'อีเมลจาก provider',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_provider (admin_id, provider),
  UNIQUE KEY unique_provider_account (provider, provider_id),
  FOREIGN KEY (admin_id) REFERENCES admin(admin_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ข้อมูล OAuth ของ admin';

-- ตัวอย่างการเพิ่มข้อมูล
-- INSERT INTO admin_oauth (admin_id, provider, provider_id, provider_email)
-- VALUES (1, 'google', 'google_user_id_here', 'user@gmail.com');
