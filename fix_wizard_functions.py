import re

with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/tenant_wizard.php', 'r') as f:
    text = f.read()

# 1. wrap openBookingModal content in try catch
def openBookingModal_replacer(m):
    return """    function openBookingModal(bkgId, tntId, roomId, tntName, tntPhone, roomNumber, typeName, typePrice, bkgDate, readOnly = false) {
        try {
            document.getElementById('modal_bkg_id').value = bkgId;
            document.getElementById('modal_booking_tnt_id').value = tntId || '';
            document.getElementById('modal_room_id').value = roomId || '';

            const bookingSubmitBtn = document.getElementById('bookingSubmitBtn');
            const bookingCloseBtn = document.getElementById('bookingCloseBtn');
            if (bookingSubmitBtn) bookingSubmitBtn.style.display = readOnly ? 'none' : 'inline-block';
            if (bookingCloseBtn) bookingCloseBtn.textContent = readOnly ? 'ปิด' : 'ยกเลิก';
            
            const bookingInfo = document.getElementById('bookingInfo');
            if (bookingInfo) {
                bookingInfo.innerHTML = `
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ผู้เช่า</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${tntName || '-'}</div>
                            <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">${tntPhone || '-'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ห้องพัก</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${roomNumber || '-'}</div>
                            <div style="font-size: 0.9rem; color: rgba(255,255,255,0.7);">${typeName || '-'}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">ราคา</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #3b82f6;">฿${Number(typePrice||0).toLocaleString()}/เดือน</div>
                        </div>
                        <div>
                            <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">วันที่จอง</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: #fff;">${bkgDate || '-'}</div>
                        </div>
                    </div>
                `;
            }
            
            const modal = document.getElementById('bookingModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                document.body.classList.add('modal-open');
            } else {
                console.error("bookingModal element not found!");
                alert("เกิดข้อผิดพลาด: ไม่พบหน้าต่างข้อมูลการจองในระบบ");
            }
        } catch (e) {
            console.error("Exception in openBookingModal", e);
            alert("เกิดข้อผิดพลาดในระบบ: " + e.message);
        }
    }"""

text = re.sub(r'\s+function openBookingModal\([^)]+\) \{[\s\S]*?document\.body\.classList\.add\(\'modal-open\'\);\n\s+\}', openBookingModal_replacer, text)


# 2. cancelBooking and doCancelBooking
def cancelBooking_replacer(m):
    return """
    // Function สำหรับยกเลิกการจอง
    async function cancelBooking(bkgId, tntId, tntName) {
        try {
            let confirmed = false;
            // Support sweetalert if exists, fallback to window.confirm
            if (typeof showConfirmDialog === 'function') {
                try {
                    confirmed = await showConfirmDialog(
                        'ยืนยันการยกเลิกการจอง',
                        `คุณต้องการยกเลิกการจองของ "${tntName}" หรือไม่?\\n\\n⚠️ ข้อมูลที่จะถูกลบ:\\n• ข้อมูลการจอง\\n• ข้อมูลการชำระเงินมัดจำ\\n• ข้อมูลสัญญา (ถ้ามี)\\n• ข้อมูลค่าใช้จ่าย (ถ้ามี)\\n\\nการดำเนินการนี้ไม่สามารถย้อนกลับได้!`,
                        'delete'
                    );
                } catch(e) {
                    console.error("showConfirmDialog throw error", e);
                    confirmed = confirm(`คุณต้องการยกเลิกการจองของ "${tntName}" หรือไม่?\\n\\nข้อมูลทั้งหมดที่เกี่ยวข้องจะถูกลบ!`);
                }
            } else {
                confirmed = confirm(`คุณต้องการยกเลิกการจองของ "${tntName}" หรือไม่?\\n\\nข้อมูลทั้งหมดที่เกี่ยวข้องจะถูกลบ!`);
            }

            if (confirmed) {
                await doCancelBooking(bkgId, tntId);
            }
        } catch (err) {
            console.error("Exception in cancelBooking", err);
            alert("เกิดข้อผิดพลาดการยกเลิก: " + err.message);
        }
    }

    async function doCancelBooking(bkgId, tntId) {
        try {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo $csrfToken; ?>');
            formData.append('bkg_id', bkgId);
            formData.append('tnt_id', tntId);

            const response = await fetch('../Manage/cancel_booking.php', {
                method: 'POST',
                body: formData
            });

            // ตรวจสอบข้อมูลก่อนเพื่อป้องกัน JSON Parse Error ถ้าระบบส่ง HTML Error กลับมา
            let textData = await response.text();
            let data;
            try {
                data = JSON.parse(textData);
            } catch(e) {
                console.error("Not a valid json response from cancel_booking.php:", textData);
                alert("ดำเนินการสำเร็จ แต่ระบบไม่สามารถรีโหลดข้อมูลอัตโนมัติได้ กรุณารีเฟรชหน้าเว็บ");
                location.reload();
                return;
            }

            if (data.success) {
                if (typeof showSuccessToast === 'function') {
                    showSuccessToast(data.message || 'ยกเลิกการจองเรียบร้อยแล้ว');
                } else {
                    alert(data.message || 'ยกเลิกการจองเรียบร้อยแล้ว');
                }
                
                try {
                    refreshWizardTable();
                } catch(e) {
                    location.reload();
                }
            } else {
                if (typeof showErrorToast === 'function') {
                    showErrorToast(data.error || 'เกิดข้อผิดพลาดในการยกเลิก');
                } else {
                    alert(data.error || 'เกิดข้อผิดพลาดในการยกเลิก');
                }
            }
        } catch (error) {
            console.error('Error cancelling booking:', error);
            if (typeof showErrorToast === 'function') {
                showErrorToast('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            } else {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์\\n' + error.message);
            }
        }
    }
"""

text = re.sub(r'\s+// Function สำหรับยกเลิกการจอง\s+async function cancelBooking\([^)]+\) \{[\s\S]*?catch \(error\) \{[\s\S]*?\}\n\s+\}', cancelBooking_replacer, text)


with open('/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Reports/tenant_wizard.php', 'w') as f:
    f.write(text)

