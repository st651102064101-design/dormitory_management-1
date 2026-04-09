import re

with open('Public/booking_status.php', 'r') as f:
    content = f.read()

old_query = """                c.ctr_id, c.ctr_deposit, c.access_token, c.ctr_start, c.ctr_end,
                e.exp_status,
                (SELECT COALESCE(SUM(CASE WHEN bp_status = '1' THEN bp_amount ELSE 0 END), 0) FROM booking_payment WHERE bkg_id = b.bkg_id) as paid_amount"""

new_query = """                c.ctr_id, c.ctr_deposit, c.access_token, c.ctr_start, c.ctr_end,
                e.exp_status,
                COALESCE(tw.current_step, 1) as current_step,
                COALESCE(tw.completed, 0) as workflow_completed,
                COALESCE(tw.step_2_confirmed, 0) as step_2_confirmed,
                (SELECT COALESCE(SUM(CASE WHEN bp_status = '1' THEN bp_amount ELSE 0 END), 0) FROM booking_payment WHERE bkg_id = b.bkg_id) as paid_amount,
                (SELECT COUNT(*) FROM signature_logs WHERE contract_id = c.ctr_id AND signer_type = 'tenant') as has_signature"""

content = content.replace(old_query, new_query)

old_join = """            LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
            LEFT JOIN expense e ON c.ctr_id = e.ctr_id"""

new_join = """            LEFT JOIN contract c ON t.tnt_id = c.tnt_id AND c.room_id = b.room_id AND c.ctr_status = '0'
            LEFT JOIN expense e ON c.ctr_id = e.ctr_id
            LEFT JOIN tenant_workflow tw ON b.bkg_id = tw.bkg_id"""

content = content.replace(old_join, new_join)

with open('Public/booking_status.php', 'w') as f:
    f.write(content)

print(content.find("COALESCE(tw.current_step, 1) as current_step"))
