import glob
import re

files = glob.glob('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Tenant/*.php')

for file in files:
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    # We want to replace `<?php if (!empty($_SESSION['tenant_logged_in'])): ?>\n ... href="../tenant_logout.php"`
    # So we look for the if statement and replace it if it's protecting the logout button.
    
    # regex matches <?php if (!empty($_SESSION['tenant_logged_in'])): ?> that is followed by the logout button within 200 chars.
    
    # Alternative:
    new_content = re.sub(
        r"<\?php if \(!empty\(\$_SESSION\['tenant_logged_in'\]\)\):\ \?>(\s*<div[^>]*>)?\s*<a href=\"\.\./tenant_logout\.php\"",
        r"<?php if (!empty($_SESSION['tenant_logged_in']) && !empty($contract['line_user_id'])): ?>\g<1>\n                <a href=\"../tenant_logout.php\"",
        content,
        flags=re.MULTILINE
    )

    # Note that in profile.php, there's no <div> wrapping the logout. 
    # `</a>            <a href="../tenant_logout.php"`
    new_content = re.sub(
        r"<\?php if \(!empty\(\$_SESSION\['tenant_logged_in'\]\)\):\ \?>(.*?href=\"\.\./tenant_logout\.php\")",
        r"<?php if (!empty($_SESSION['tenant_logged_in']) && !empty($contract['line_user_id'])): ?>\g<1>",
        content,
        flags=re.DOTALL
    )
    
    if new_content != content:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Fixed: {file}")

