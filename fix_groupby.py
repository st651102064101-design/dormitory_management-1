with open('Public/booking_status.php', 'r') as f:
    content = f.read()

content = content.replace(
    "GROUP BY t.tnt_id, b.bkg_id, r.room_id, rt.type_id, c.ctr_id, e.exp_id",
    "GROUP BY t.tnt_id, b.bkg_id, r.room_id, rt.type_id, c.ctr_id, e.exp_id, tw.current_step, tw.completed, tw.step_2_confirmed"
)

with open('Public/booking_status.php', 'w') as f:
    f.write(content)
