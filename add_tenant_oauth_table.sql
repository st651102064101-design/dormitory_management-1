-- สร้างตารางสำหรับเก็บข้อมูล OAuth ของผู้เช่า
-- เพื่อให้ผู้เช่าสามารถล็อกอินผ่าน Google, Facebook, LINE ได้

CREATE TABLE IF NOT EXISTS tenant_oauth (
  oauth_id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  provider VARCHAR(50) NOT NULL COMMENT 'google, facebook, line',
  provider_id VARCHAR(255) NOT NULL COMMENT 'ID จาก provider',
  provider_email VARCHAR(255) NOT NULL COMMENT 'อีเมลจาก provider',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_provider (tenant_id, provider),
  UNIQUE KEY unique_provider_account (provider, provider_id),
  FOREIGN KEY (tenant_id) REFERENCES tenant(tenant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ข้อมูล OAuth ของผู้เช่า';

-- ตัวอย่างการเพิ่มข้อมูล
-- INSERT INTO tenant_oauth (tenant_id, provider, provider_id, provider_email)
-- VALUES (1, 'google', 'google_user_id_here', 'tenant@gmail.com');
