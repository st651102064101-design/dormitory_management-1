import os
import glob

files = glob.glob('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Tenant/*.php')

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Replace the specific SQL logic block anywhere it appears
    # it might have different indentations
    import re
    
    # regex to match: AND (\n e.exp_month = ... OR EXISTS (...) )
    pattern = r"AND\s*\(\s*(?:--[^\n]*\n\s*)?e\.exp_month\s*=\s*\(SELECT\s+MIN\(e2\.exp_month\)\s+FROM\s+expense\s+e2\s+WHERE\s+e2\.ctr_id\s*=\s*e\.ctr_id\)\s+OR\s+(EXISTS\s*\(\s*SELECT\s+1\s+FROM\s+utility\s+u\s+WHERE\s+u\.ctr_id\s*=\s*e\.ctr_id\s+AND\s+YEAR\(u\.utl_date\)\s*=\s*YEAR\(e\.exp_month\)\s+AND\s+MONTH\(u\.utl_date\)\s*=\s*MONTH\(e\.exp_month\)\s+AND\s+u\.utl_water_end\s*IS\s*NOT\s*NULL\s+AND\s+u\.utl_elec_end\s*IS\s*NOT\s*NULL\s*\))\s*\)"
    
    new_content = re.sub(pattern, r"AND \g<1>", content, flags=re.MULTILINE|re.IGNORECASE)
    
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Fixed: {file}")

