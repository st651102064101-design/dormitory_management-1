-- ===================================================
-- Ensure Rooms 2 and 12 Exist in Database
-- ===================================================

-- 1. Check if room table exists
-- If it doesn't, the system will create it via migration

-- 2. Find the default room type ID
SET @defaultTypeId = (SELECT type_id FROM roomtype LIMIT 1);

-- If no room type exists, we need to create one first
-- This shouldn't happen in normal operation, but adding safety check
IF @defaultTypeId IS NULL THEN
  INSERT INTO roomtype (type_name, type_price) VALUES ('Standard Room', 5000);
  SET @defaultTypeId = LAST_INSERT_ID();
END IF;

-- 3. Check if rooms 2 and 12 exist, and create if missing
-- Room 2
IF NOT EXISTS (SELECT 1 FROM room WHERE room_number = 2) THEN
  INSERT INTO room (room_number, type_id) VALUES (2, @defaultTypeId);
  SELECT 'Room 2 created' as status;
ELSE
  SELECT 'Room 2 already exists' as status;
END IF;

-- Room 12
IF NOT EXISTS (SELECT 1 FROM room WHERE room_number = 12) THEN
  INSERT INTO room (room_number, type_id) VALUES (12, @defaultTypeId);
  SELECT 'Room 12 created' as status;
ELSE
  SELECT 'Room 12 already exists' as status;
END IF;

-- 4. Verify the rooms now exist
SELECT 'Verification: Current Rooms' as section;
SELECT room_id, room_number, type_id FROM room WHERE room_number IN (2, 12);

-- 5. Show any repairs that exist for these rooms via contracts
SELECT 'Verification: Repairs for Rooms 2 and 12' as section;
SELECT 
  r.repair_id, 
  r.ctr_id, 
  r.repair_date, 
  r.repair_status,
  c.room_id,
  rm.room_number,
  t.tnt_name
FROM repair r
LEFT JOIN contract c ON r.ctr_id = c.ctr_id
LEFT JOIN room rm ON c.room_id = rm.room_id
LEFT JOIN tenant t ON c.tnt_id = t.tnt_id
WHERE rm.room_number IN (2, 12) OR c.room_id IN (SELECT room_id FROM room WHERE room_number IN (2, 12))
ORDER BY r.repair_date DESC;
