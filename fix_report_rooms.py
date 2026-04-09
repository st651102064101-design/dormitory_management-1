import re

with open('Reports/report_rooms.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_logic = """$pdo->exec("UPDATE room SET room_status = '0'");
// NOTE: ห้องจะเป็น "ไม่ว่าง" (1) เฉพาะเมื่อมีผู้เช่าเข้าพักแล้วเท่านั้น (checkin_record มีข้อมูล)
$pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (
    SELECT 1 FROM checkin_record cr
    INNER JOIN contract c ON cr.ctr_id = c.ctr_id
    WHERE c.room_id = room.room_id
    AND (
        c.ctr_status IN ('0','2')
        OR (c.ctr_status = '1' AND EXISTS (
            SELECT 1 FROM termination t WHERE t.ctr_id = c.ctr_id AND t.term_date > CURDATE()
        ))
    )
)");"""

new_logic = """$pdo->exec("UPDATE room SET room_status = '0'");
// อัพเดทสถานะห้องเป็น "ไม่ว่าง" สำหรับห้องที่มีสัญญาใช้งานอยู่ หรือมีการจองที่ได้รับการยืนยัน
$pdo->exec("UPDATE room SET room_status = '1' WHERE EXISTS (
    SELECT 1 FROM contract c WHERE c.room_id = room.room_id AND c.ctr_status = '0'
) OR EXISTS (
    SELECT 1 FROM booking b WHERE b.room_id = room.room_id AND b.bkg_status = '1'
)");"""

content = content.replace(old_logic, new_logic)

with open('Reports/report_rooms.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Replaced logic")
