import re

with open('Reports/dashboard.php', 'r', encoding='utf-8') as f:
    content = f.read()

# match all text containing at least one Thai character: [\u0e00-\u0e7f]
thai_strings = set()
for match in re.finditer(r'([^\s\'"><{}()$]+[\u0e00-\u0e7f]+[^\s\'"><{}()$]*)', content):
    line_text = match.group(1)
    # Actually, better to just extract whole lines that have Thai text, and manually pick what to swap
    pass

# We already swapped the most important ones. Let's see what is left:
lines_with_thai = []
for i, line in enumerate(content.split('\n')):
    if re.search(r'[\u0e00-\u0e7f]', line) and not line.strip().startswith('//') and not 'error_log' in line:
        lines_with_thai.append((i+1, line.strip()))

for i, l in lines_with_thai:
    print(f"{i}: {l}")
