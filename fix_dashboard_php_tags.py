import re

with open('Reports/dashboard.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Split the content into the top PHP block and the rest
parts = content.split('<!DOCTYPE', 1)
top_php = parts[0]

# Fix occurrences of <?php echo __('xyz'); ?> inside the top PHP block
top_php = re.sub(r"<\?php\s+echo\s+__\('([^']+)'\);\s*\?>", r"__('\1')", top_php)

if len(parts) > 1:
    new_content = top_php + '<!DOCTYPE' + parts[1]
else:
    new_content = top_php

with open('Reports/dashboard.php', 'w', encoding='utf-8') as f:
    f.write(new_content)

print('Done')
