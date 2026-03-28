-- Create March 2026 utility record for room 12 (first reading)
-- Room 12 is room_id = 12
-- Contract ID = 770179299

INSERT INTO utility (ctr_id, utl_water_start, utl_water_end, utl_elec_start, utl_elec_end, utl_date)
SELECT 770179299, 0, 1044, 0, 1044, '2026-03-29'
WHERE NOT EXISTS (
    SELECT 1 FROM utility 
    WHERE ctr_id = 770179299 AND MONTH(utl_date) = 3 AND YEAR(utl_date) = 2026
);
