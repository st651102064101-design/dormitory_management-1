import re
with open("Reports/settings/settings_data.php", "r", encoding="utf-8") as f:
    c = f.read()

c = c.replace("$wsUrl = 'ws://localhost:8080';", "$wsUrl = 'ws://localhost:8080';\n$wsPort = '8080';\n$wsHost = '0.0.0.0';")
c = c.replace("'ws_enabled', 'ws_url')", "'ws_enabled', 'ws_url', 'ws_port', 'ws_host')")
c = c.replace("$wsUrl = $settings['ws_url'] ?? $wsUrl;", "$wsUrl = $settings['ws_url'] ?? $wsUrl;\n    $wsPort = $settings['ws_port'] ?? $wsPort;\n    $wsHost = $settings['ws_host'] ?? $wsHost;")

with open("Reports/settings/settings_data.php", "w", encoding="utf-8") as f:
    f.write(c)
print("settings patched")
