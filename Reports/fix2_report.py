import sys
import re

file_path = "/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/report_reservations.php"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Re-add datatable-modern.css
content = content.replace(
    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />',
    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.4/dist/style.css" />\n    <link rel="stylesheet" href="/dormitory_management/Public/Assets/Css/datatable-modern.css" />'
)

# 2. Fix the buttons to match the exact same colors "เหมือนหน้านอื่น" (bg-blue-500 for active, bg-slate-100 for inactive)
# The current unselected is 'bg-transparent text-slate-600 hover:bg-slate-100' or similar
content = re.sub(
    r"\<\?php echo (!isset\(\$_GET\['status'\]\)) \? '[^']+' : '[^']+'; \?\>",
    r"<?php echo \1 ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>",
    content
)
content = re.sub(
    r"\<\?php echo (isset\(\$_GET\['status'\]\) && \$_GET\['status'\] === '1') \? '[^']+' : '[^']+'; \?\>",
    r"<?php echo \1 ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>",
    content
)
content = re.sub(
    r"\<\?php echo (isset\(\$_GET\['status'\]\) && \$_GET\['status'\] === '2') \? '[^']+' : '[^']+'; \?\>",
    r"<?php echo \1 ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>",
    content
)
content = re.sub(
    r"\<\?php echo (isset\(\$_GET\['status'\]\) && \$_GET\['status'\] === '0') \? '[^']+' : '[^']+'; \?\>",
    r"<?php echo \1 ? 'bg-blue-500 border border-blue-500 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 border border-slate-200 text-slate-700 hover:bg-slate-100'; ?>",
    content
)

# 3. Strip tailwind classes from the table to let datatable-modern.css work properly
content = content.replace('<table id="table-reservations" class="saas-table">', '<table id="table-reservations">')
# Remove all font-bold text-slate-800 etc from tds
content = re.sub(r'<td class="font-bold text-slate-800">', r'<td>', content)
content = re.sub(r'<td class="font-medium text-slate-700">', r'<td>', content)
content = re.sub(r'<tr class="transition-colors group cursor-pointer hover:bg-slate-50">', r'<tr>', content)

# 4. Remove .saas-table CSS
content = re.sub(r'/\* Table Styles for Light Theme \*/.*?(?=/\* Scrollbar \*/)', '', content, flags=re.DOTALL)

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)
print("Updated HTML.")
