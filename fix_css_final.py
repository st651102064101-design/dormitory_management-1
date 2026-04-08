import re
with open("Reports/settings/apple-settings.css", "r", encoding="utf-8") as f:
    css = f.read()

new_css = """/* LINE OA Custom Row Icon Styles */
.apple-row-icon.line-oa-icon {
  background: linear-gradient(135deg, #00C300, #00A300) !important;
  box-shadow: 0 3px 10px rgba(0, 195, 0, 0.3) !important;
}

.apple-row-icon.line-oa-icon svg {
  color: #ffffff;
  stroke: #ffffff;
  display: block;
}

.apple-row-icon.line-oa-icon svg path,
.apple-row-icon.line-oa-icon svg circle,
.apple-row-icon.line-oa-icon svg polyline,
.apple-row-icon.line-oa-icon svg line {
  stroke: #ffffff !important;
  stroke-width: 2 !important;
  stroke-dasharray: none !important;
  stroke-dashoffset: 0 !important;
  animation: none !important;
  opacity: 1 !important;
  visibility: visible !important;
  display: block !important;
}

.apple-settings-row:hover .apple-row-icon.line-oa-icon {
  transform: scale(1.12) rotate(-3deg);
}
"""

pattern = r"/\* LINE OA Custom Row Icon Styles \*/(.|\n)*"
css = re.sub(pattern, new_css, css)

with open("Reports/settings/apple-settings.css", "w", encoding="utf-8") as f:
    f.write(css)
print("Updated CSS with fixes.")
