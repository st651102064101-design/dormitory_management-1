import re
with open('Reports/settings/section_system.php', 'r', encoding='utf-8') as f:
    text = f.read()

pattern_script = re.compile(r'<script>\s*\(function bindInlineQuickActionsSubmit[\s\S]*?</script>')
matches = pattern_script.findall(text)

if len(matches) > 0:
    good_script = matches[0]
    # Remove the location.reload call
    good_script = re.sub(r'setTimeout\(\(\)\s*=>\s*\{\s*window\.location\.reload\(\);\s*\},\s*1000\);', '', good_script)
    
    text = pattern_script.sub('', text)
    
    text = re.sub(r'(<button type="submit"[^>]*>บันทึกปุ่มลัด</button>\s*</form>)\s*</div>', r'\1\n        ' + good_script + '\n      </div>', text)

with open('Reports/settings/section_system.php', 'w', encoding='utf-8') as f:
    f.write(text)

print('Done settings')
