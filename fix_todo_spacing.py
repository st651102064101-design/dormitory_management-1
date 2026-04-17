import re

file_path = '/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/todo_tasks.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace border-spacing
content = content.replace('border-spacing: 0;', 'border-spacing: 0 12px;')

# Change thead th padding
content = content.replace('padding: 12px 16px;\n            border-bottom: 1px solid rgba(255,255,255,0.08);', 
                          'padding: 12px 16px;\n            border-bottom: none;')

# Change tbody td padding/background
old_td = """        .todo-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            vertical-align: middle;
        }"""
new_td = """        .todo-table tbody td {
            padding: 16px 20px;
            background: rgba(255,255,255,0.03);
            border-bottom: none;
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            vertical-align: middle;
        }
        .todo-table tbody td:first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        .todo-table tbody td:last-child {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }"""
content = content.replace(old_td, new_td)

# Adjust light mode
old_light = "body.live-light .todo-table tbody td { color: rgba(0,0,0,0.8); border-bottom-color: rgba(0,0,0,0.06); }"
new_light = "body.live-light .todo-table tbody td { color: rgba(0,0,0,0.8); background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); border-bottom-color: transparent; border-right: none; border-left: none; }\n        body.live-light .todo-table tbody td:first-child { border-left: 1px solid rgba(0,0,0,0.05); }\n        body.live-light .todo-table tbody td:last-child { border-right: 1px solid rgba(0,0,0,0.05); }"
content = content.replace(old_light, new_light)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Patch applied.")
