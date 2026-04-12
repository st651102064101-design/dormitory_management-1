import re

with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/tenant_wizard.php', 'r') as f:
    text = f.read()

def openPaymentModal_replacer(m):
    return """    // Functions สำหรับ Payment Modal (Step 2)
    function openPaymentModal(bpId, bkgId, tntId, tntName, roomNumber, bpAmount, bpProof, readOnly = false) {
        try {
            document.getElementById('modal_payment_bp_id').value = bpId || '';
            document.getElementById('modal_payment_bkg_id').value = bkgId || '';
            document.getElementById('modal_payment_tnt_id').value = tntId || '';

            const paymentSubmitBtn = document.getElementById('paymentSubmitBtn');
            const paymentCloseBtn = document.getElementById('paymentCloseBtn');
            if (paymentSubmitBtn) paymentSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
            if (paymentCloseBtn) paymentCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
            
            const paymentInfo = document.getElementById('paymentInfo');
            if (paymentInfo) {
                paymentInfo.innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">ผู้เช่า</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #0f172a;">${tntName || '-'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">ห้องพัก</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #0f172a;">${roomNumber || '-'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 4px;">จำนวนเงินจอง</div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #059669;">฿${Number(bpAmount||0).toLocaleString()}</div>
                        </div>
                    </div>
                `;
            }
            
            const proofContainer = document.getElementById('paymentProofContainer');
            if (proofContainer) {
                if (bpProof) {
                    let proofUrl = bpProof;
                    // Fix path
                    if (!proofUrl.startsWith('/')) {
                        proofUrl = '/' + proofUrl;
                    }
                    if (!proofUrl.includes('dormitory_management')) {
                        proofUrl = '/dormitory_management/Public/Assets/Images/Payments' + proofUrl;
                    }
                    const paymentProofImg = document.getElementById('paymentProofImg');
                    if (paymentProofImg) paymentProofImg.src = proofUrl;
                    
                    const paymentProofLink = document.getElementById('paymentProofLink');
                    if (paymentProofLink) paymentProofLink.href = proofUrl;
                    
                    proofContainer.style.display = 'block';
                } else {
                    proofContainer.style.display = 'none';
                }
            }
            
            const modal = document.getElementById('paymentModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                document.body.classList.add('modal-open');
            } else {
                console.error("paymentModal element not found!");
                alert("เกิดข้อผิดพลาด: ไม่พบหน้าต่างข้อมูลการชำระเงินในระบบ");
            }
        } catch (e) {
            console.error("Exception in openPaymentModal", e);
            alert("เกิดข้อผิดพลาดในระบบ: " + e.message);
        }
    }"""

text = re.sub(r'\s+// Functions สำหรับ Payment Modal \(Step 2\)\n\s+function openPaymentModal\([^)]+\) \{[\s\S]*?document\.body\.classList\.add\(\'modal-open\'\);\n\s+\}', openPaymentModal_replacer, text)
with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/tenant_wizard.php', 'w') as f:
    f.write(text)

