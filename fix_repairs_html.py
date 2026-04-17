import re

with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/report_repairs.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix inline colors for stat values
content = content.replace('style="color:#fbbf24;"', 'style="color:#d97706;"')
content = content.replace('style="color:#60a5fa;"', 'style="color:#0284c7;"')
content = content.replace('style="color:#22c55e;"', 'style="color:#16a34a;"')

# Fix inline styles for filter buttons
def fix_button(match):
    full_match, status_var, val = match.groups()
    if status_var == "(!isset($_GET['status']))":
        cond = "(!isset($_GET['status']))"
    else:
        cond = f"($selectedStatus === '{val}')"
        
    bg = f"<?php echo {cond} ? '#0ea5e9' : '#ffffff'; ?>"
    color = f"<?php echo {cond} ? '#fff' : '#64748b'; ?>"
    border = f"<?php echo {cond} ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>"
    stroke = f"<?php echo {cond} ? '#ffffff' : 'currentColor'; ?>"
    
    # We replace the inline style
    return re.sub(r'style="[^"]*"', f'style="padding:0.75rem 1.5rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;background:{bg};color:{color};border:{border};border-radius:8px;font-weight:600;font-size:0.9rem;transition:all 0.2s;box-shadow:0 1px 2px 0 rgba(0,0,0,0.05);"', full_match)

# We can just do a simple replace on the known strings for the 4 buttons:
btn_styles = {
    "background:<?php echo (!isset($_GET['status'])) ? '#60a5fa' : 'rgba(255,255,255,0.05)'; ?>;color:<?php echo (!isset($_GET['status'])) ? '#fff' : '#94a3b8'; ?>;": "background:<?php echo (!isset($_GET['status'])) ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo (!isset($_GET['status'])) ? '#ffffff' : '#64748b'; ?>;border:<?php echo (!isset($_GET['status'])) ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;",
    "background:<?php echo $selectedStatus === '0' ? '#60a5fa' : 'rgba(255,255,255,0.05)'; ?>;color:<?php echo $selectedStatus === '0' ? '#fff' : '#94a3b8'; ?>;": "background:<?php echo $selectedStatus === '0' ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo $selectedStatus === '0' ? '#ffffff' : '#64748b'; ?>;border:<?php echo $selectedStatus === '0' ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;",
    "background:<?php echo $selectedStatus === '1' ? '#60a5fa' : 'rgba(255,255,255,0.05)'; ?>;color:<?php echo $selectedStatus === '1' ? '#fff' : '#94a3b8'; ?>;": "background:<?php echo $selectedStatus === '1' ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo $selectedStatus === '1' ? '#ffffff' : '#64748b'; ?>;border:<?php echo $selectedStatus === '1' ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;",
    "background:<?php echo $selectedStatus === '2' ? '#60a5fa' : 'rgba(255,255,255,0.05)'; ?>;color:<?php echo $selectedStatus === '2' ? '#fff' : '#94a3b8'; ?>;": "background:<?php echo $selectedStatus === '2' ? '#0ea5e9' : '#ffffff'; ?>;color:<?php echo $selectedStatus === '2' ? '#ffffff' : '#64748b'; ?>;border:<?php echo $selectedStatus === '2' ? '1px solid #0ea5e9' : '1px solid #cbd5e1'; ?>;border-radius:8px;font-weight:600;transition:all 0.2s;"
}

for old_s, new_s in btn_styles.items():
    content = content.replace(old_s, new_s)

# Inside cards, replace color:#fff with color:#0f172a and color:#94a3b8 with color:#64748b
content = content.replace('<div style="font-size:1.05rem;font-weight:600;color:#fff;margin-bottom:0.75rem;">', '<div style="font-size:1.05rem;font-weight:600;color:#0f172a;margin-bottom:0.75rem;">')
content = content.replace('<span style="color:#fff;font-weight:600;">', '<span style="color:#0f172a;font-weight:600;">')
content = content.replace('<div style="color:#fff;">', '<div style="color:#0f172a;font-weight:500;">')

content = content.replace('color:#94a3b8;', 'color:#64748b;')
content = content.replace('background:#a7f3d0;color:#065f46;', 'background:#d1fae5;color:#065f46;')

with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/report_repairs.php', 'w', encoding='utf-8') as f:
    f.write(content)

