with open('Reports/dashboard.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_av = "$stmt = $pdo->query(\"SELECT COUNT(*) as total FROM room WHERE room_status = 1\");\n    $room_available = $stmt->fetch()['total'] ?? 0;"
new_av = "$stmt = $pdo->query(\"SELECT COUNT(*) as total FROM room WHERE room_status = 0\");\n    $room_available = $stmt->fetch()['total'] ?? 0;"

old_occ = "$stmt = $pdo->query(\"SELECT COUNT(*) as total FROM room WHERE room_status = 0\");\n    $room_occupied = $stmt->fetch()['total'] ?? 0;"
new_occ = "$stmt = $pdo->query(\"SELECT COUNT(*) as total FROM room WHERE room_status = 1\");\n    $room_occupied = $stmt->fetch()['total'] ?? 0;"

content = content.replace(old_av, new_av).replace(old_occ, new_occ)

with open('Reports/dashboard.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed dashboard counting")
