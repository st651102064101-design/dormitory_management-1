import os
import re

files_to_process = [
    'Reports/tenant_wizard.php',
    'includes/sidebar.php',
    'includes/wizard_helper.php'
]

patterns = [
    # tenant_wizard.php first_exp_status / current_exp_status / latest_exp_status
    (r"AND COALESCE\(\(SELECT SUM\(p\.pay_amount\) FROM payment p WHERE p\.exp_id = e\.exp_id AND p\.pay_status = '1' AND TRIM\(COALESCE\(p\.pay_remark,''\)\) <> 'มัดจำ'\), 0\) >= \(e\.exp_total\) - 0\.00001",
     r"AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark,'')) <> 'มัดจำ'), 0) >= (e.exp_total - COALESCE((SELECT SUM(p2.pay_amount) FROM payment p2 WHERE p2.exp_id = e.exp_id AND p2.pay_status IN ('0','1') AND TRIM(COALESCE(p2.pay_remark,'')) = 'มัดจำ'), 0)) - 0.00001"),

    # tenant_wizard.php e_first / e_latest in where clauses
    (r"AND COALESCE\(\(SELECT SUM\(p\.pay_amount\) FROM payment p \s*WHERE p\.exp_id = e_first\.exp_id AND p\.pay_status = '1' \s*AND TRIM\(COALESCE\(p\.pay_remark, ''\)\) <> 'มัดจำ'\), 0\) >= e_first\.exp_total - 0\.00001",
     r"AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e_first.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'), 0) >= (e_first.exp_total - COALESCE((SELECT SUM(p2.pay_amount) FROM payment p2 WHERE p2.exp_id = e_first.exp_id AND p2.pay_status IN ('0','1') AND TRIM(COALESCE(p2.pay_remark,'')) = 'มัดจำ'), 0)) - 0.00001"),

    (r"AND COALESCE\(\(SELECT SUM\(p\.pay_amount\) FROM payment p \s*WHERE p\.exp_id = e_latest\.exp_id AND p\.pay_status = '1'\s*AND TRIM\(COALESCE\(p\.pay_remark, ''\)\) <> 'มัดจำ'\), 0\) >= e_latest\.exp_total - 0\.00001",
     r"AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e_latest.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'), 0) >= (e_latest.exp_total - COALESCE((SELECT SUM(p2.pay_amount) FROM payment p2 WHERE p2.exp_id = e_latest.exp_id AND p2.pay_status IN ('0','1') AND TRIM(COALESCE(p2.pay_remark,'')) = 'มัดจำ'), 0)) - 0.00001"),

    # sql formatting in includes/sidebar.php, etc. (uses multiple tabs/spaces)
    (r"AND COALESCE\(\(SELECT SUM\(p\.pay_amount\) FROM payment p\s*WHERE p\.exp_id = e_first\.exp_id AND p\.pay_status = '1'\s*AND TRIM\(COALESCE\(p\.pay_remark, ''\)\) <> 'มัดจำ'\), 0\) >= e_first\.exp_total - 0\.00001",
     r"AND COALESCE((SELECT SUM(p.pay_amount) FROM payment p WHERE p.exp_id = e_first.exp_id AND p.pay_status = '1' AND TRIM(COALESCE(p.pay_remark, '')) <> 'มัดจำ'), 0) >= (e_first.exp_total - COALESCE((SELECT SUM(p2.pay_amount) FROM payment p2 WHERE p2.exp_id = e_first.exp_id AND p2.pay_status IN ('0','1') AND TRIM(COALESCE(p2.pay_remark,'')) = 'มัดจำ'), 0)) - 0.00001")
]

for file_path in files_to_process:
    full_path = os.path.join('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management', file_path)
    if os.path.exists(full_path):
        with open(full_path, 'r') as f:
            content = f.read()
        
        orig_content = content
        for p, r in patterns:
            content = re.sub(p, r, content, flags=re.MULTILINE)
            
        if orig_content != content:
            with open(full_path, 'w') as f:
                f.write(content)
            print(f"Fixed {file_path}")
        else:
            print(f"No changes matched in {file_path} (or already fixed)")
