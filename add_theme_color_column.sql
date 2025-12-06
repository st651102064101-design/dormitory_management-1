-- Add theme_color column to system_settings table
ALTER TABLE system_settings 
ADD COLUMN theme_color VARCHAR(7) DEFAULT '#0f172a' AFTER logo_filename;

-- Verify the column was added
DESCRIBE system_settings;
