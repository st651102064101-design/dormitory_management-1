-- สคริปต์แก้ไขข้อมูล rate ที่บันทึกคูณ 100 ไว้
-- ระบบจะตรวจสอบและแก้ไขเฉพาะ rate ที่มากกว่า 100 (น่าจะคูณ 100 ไว้แล้ว)

-- แก้ไข rate_elec ที่มากกว่า 100 (หาร 100)
UPDATE expense 
SET rate_elec = ROUND(rate_elec / 100)
WHERE rate_elec > 100;

-- แก้ไข rate_water ที่มากกว่า 100 (หาร 100)
UPDATE expense 
SET rate_water = ROUND(rate_water / 100)
WHERE rate_water > 100;

-- แสดงข้อมูลหลังแก้ไข
SELECT exp_id, exp_month, exp_elec_unit, exp_water_unit, rate_elec, rate_water, 
       exp_elec_chg, exp_water, exp_total
FROM expense
ORDER BY exp_id;
