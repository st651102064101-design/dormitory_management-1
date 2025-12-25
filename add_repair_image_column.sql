-- Add repair_image column to repair table if it doesn't exist
ALTER TABLE repair ADD COLUMN repair_image VARCHAR(255) DEFAULT NULL AFTER repair_desc;
