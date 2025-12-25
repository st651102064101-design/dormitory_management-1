-- เพิ่ม column effective_date ถ้ายังไม่มี
ALTER TABLE rate ADD COLUMN IF NOT EXISTS effective_date DATE DEFAULT NULL;
ALTER TABLE rate ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- อัพเดท record เดิมให้มีวันที่
UPDATE rate SET effective_date = '2025-01-01' WHERE effective_date IS NULL;
