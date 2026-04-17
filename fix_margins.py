import os
import glob

files = [
    "Reports/manage_revenue.php",
    "Reports/report_rooms.php",
    "Reports/report_payments.php",
    "Reports/report_invoice.php",
    "Reports/report_repairs.php",
    "Reports/report_news.php",
    "Reports/print_contract.php",
    "Reports/tenant_wizard.php",
    "Reports/manage_booking.php",
    "Reports/manage_utility.php",
    "Reports/manage_expenses.php",
    "Reports/manage_payments.php",
    "Reports/manage_repairs.php",
    "Reports/manage_tenants.php",
    "Reports/manage_contracts.php",
    "Reports/qr_codes.php",
    "Reports/manage_rooms.php",
    "Reports/manage_news.php"
]

injection = """
<style>
/* Auto-injected max-width styling to match dashboard / report_tenants */
.app-main > div:not(.sidebar), .main-content > div:not(.sidebar), .reports-container > div:not(.sidebar) {
    max-width: 1280px !important;
    margin: 0 auto !important;
    padding: 20px !important;
    box-sizing: border-box;
}
@media (max-width: 768px) {
    .app-main > div:not(.sidebar), .main-content > div:not(.sidebar), .reports-container > div:not(.sidebar) {
        padding: 10px !important;
    }
}
</style>
"""

affected_files = []
for file in files:
    if os.path.exists(file):
        with open(file, "r") as f:
            content = f.read()
        
        # Inject just before </head> if exists
        if "</head>" in content and "Auto-injected max-width" not in content:
            new_content = content.replace("</head>", injection + "\n</head>")
            with open(file, "w") as f:
                f.write(new_content)
            affected_files.append(file)
            
print("Fixed:", affected_files)
