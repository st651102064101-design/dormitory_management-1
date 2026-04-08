import re
with open("Reports/settings/apple-settings.css", "r", encoding="utf-8") as f:
    css = f.read()

# Replace the LINE OA Custom Row Icon Styles
pattern = r"/\* LINE OA Custom Row Icon Styles \*/(.|\n)*"

new_css = """/* LINE OA Custom Row Icon Styles */
.apple-row-icon.line-oa-icon {
  background: linear-gradient(135deg, #00C300, #00A300);
  box-shadow: 0 3px 10px rgba(0, 195, 0, 0.3);
}

.apple-row-icon.line-oa-icon svg {
  color: #ffffff;
  stroke: #ffffff;
}

.apple-settings-row:hover .apple-row-icon.line-oa-icon {
  transform: scale(1.12) rotate(-3deg);
}
"""

if "LINE OA Custom Row Icon Styles" in css:
    css = re.sub(pattern, new_css, css)
    with open("Reports/settings/apple-settings.css", "w", encoding="utf-8") as f:
        f.write(css)
    print("Fixed CSS file.")
else:
    print("Pattern not found in CSS.")
