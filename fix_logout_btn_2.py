import glob
import re

files = glob.glob('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Tenant/*.php')

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    # The <a> tag spans multiple lines. We need to match <a href="../tenant_logout.php" ... </a>
    if "tenant_logout.php" not in content:
        continue
        
    # We will wrap it with <?php if (!empty($contract['line_user_id'])): ?> ... <?php endif; ?>
    # But in index.php, the variable is $contractData sometimes, but let's check if $contract is available.
    # $contract = $contractData is done early in index.php, so $contract is universally safe!
    
    # Matching the <a> tag
    pattern = r'(<a href="\.\./tenant_logout\.php"[^>]*>.*?</a>)'
    
    # Make sure we don't double wrap
    if "if (!empty($contract['line_user_id']))" in content and "tenant_logout" in content:
        print(f"Skipping {file} as it seems already wrapped.")
        continue
        
    replacement = r"<?php if (!empty($contract['line_user_id'])): ?>\n                \g<1>\n                <?php endif; ?>"
    
    new_content = re.sub(pattern, replacement, content, flags=re.DOTALL)
    
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Fixed: {file}")

