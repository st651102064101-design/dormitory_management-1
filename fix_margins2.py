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
/* Fix margins all report pages v2 */
main > div:first-of-type,
.app-main > div:first-of-type, 
.main-content > div:first-of-type, 
.reports-container > div:first-of-type {
    max-width: 1280px !important;
    margin: 0 auto !important;
    padding: 20px !important;
    box-sizing: border-box;
}
@media (max-width: 768px) {
    main > div:first-of-type,
    .app-main > div:first-of-type, 
    .main-content > div:first-of-type, 
    .reports-container > div:first-of-type {
        padding: 10px !important;
    }
}
</style>
"""

for file in files:
    if os.path.exists(file):
        with open(file, "r") as f:
            content = f.read()
        
        # Remove previous injection
        if "/* Auto-injected max-width styling to match dashboard / report_tenants */" in content:
            import re
            content = re.sub(r'<style>\s*/\* Auto-injected max-width styling.*?</style>', '', content, flags=re.DOTALL)
            
        if "</head>" in content and "/* Fix margins all report pages v2 */" not in content:
            content = content.replace("</head>", injection + "\n</head>")
            with open(file, "w") as f:
                f.write(content)
print("Done v2")
