<!-- Signature Pad Modal -->
<style>
/* ===== Signature Modal Styles ===== */
.signature-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 99999;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    animation: fadeIn 0.3s ease;
}

.signature-modal-overlay.active {
    display: flex;
    justify-content: center;
    align-items: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.signature-modal {
    background: #ffffff;
    border-radius: 20px;
    max-width: 500px;
    width: 95%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.signature-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
}

.signature-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}

.signature-modal-close {
    width: 36px;
    height: 36px;
    border: none;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.signature-modal-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.signature-modal-close svg {
    width: 18px;
    height: 18px;
}

.signature-modal-body {
    padding: 24px;
}

.signature-info {
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    border: 1px solid #93c5fd;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.signature-info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
}

.signature-info-row:last-child {
    margin-bottom: 0;
}

.signature-info-label {
    color: #64748b;
    font-weight: 500;
}

.signature-info-value {
    color: #1e293b;
    font-weight: 600;
}

.signature-pad-container {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    background: #fafafa;
    padding: 8px;
    margin-bottom: 16px;
    transition: border-color 0.2s;
}

.signature-pad-container:hover,
.signature-pad-container.active {
    border-color: #3b82f6;
    background: #f0f9ff;
}

.signature-pad-canvas {
    width: 100%;
    height: 200px;
    border-radius: 8px;
    background: #ffffff;
    touch-action: none;
    cursor: crosshair;
}

.signature-pad-hint {
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
    margin-top: 8px;
}

.signature-pad-hint svg {
    width: 16px;
    height: 16px;
    vertical-align: -3px;
    margin-right: 4px;
}

.signature-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}

.signature-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}

.signature-btn svg {
    width: 18px;
    height: 18px;
}

.signature-btn-clear {
    background: #f1f5f9;
    color: #64748b;
}

.signature-btn-clear:hover {
    background: #e2e8f0;
    color: #475569;
}

.signature-btn-confirm {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
}

.signature-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
}

.signature-btn-confirm:disabled {
    background: #cbd5e1;
    color: #94a3b8;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.signature-legal {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 10px;
    padding: 14px;
    font-size: 12px;
    color: #92400e;
    line-height: 1.6;
}

.signature-legal strong {
    display: block;
    margin-bottom: 4px;
    color: #78350f;
}

.signature-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
    font-size: 11px;
    color: #94a3b8;
    text-align: center;
}

/* Loading state */
.signature-btn-confirm.loading {
    pointer-events: none;
}

.signature-btn-confirm.loading::after {
    content: '';
    width: 18px;
    height: 18px;
    border: 2px solid #ffffff;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-left: 8px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Success animation */
.signature-success {
    display: none;
    text-align: center;
    padding: 40px;
}

.signature-success.show {
    display: block;
}

.signature-success svg {
    width: 80px;
    height: 80px;
    color: #22c55e;
    margin-bottom: 20px;
}

.signature-success h4 {
    font-size: 20px;
    color: #1e293b;
    margin-bottom: 8px;
}

.signature-success p {
    color: #64748b;
    font-size: 14px;
}

/* Mobile responsive */
@media (max-width: 480px) {
    .signature-modal {
        width: 100%;
        max-width: 100%;
        border-radius: 20px 20px 0 0;
        max-height: 95vh;
    }
    
    .signature-modal-overlay.active {
        align-items: flex-end;
    }
    
    .signature-pad-canvas {
        height: 180px;
    }
    
    .signature-actions {
        flex-direction: column;
    }
}

/* Print: hide modal */
@media print {
    .signature-modal-overlay {
        display: none !important;
    }
}
</style>

<div class="signature-modal-overlay" id="signatureModal">
    <div class="signature-modal">
        <div class="signature-modal-header">
            <h3>✍️ ลงลายมือชื่อ</h3>
            <button class="signature-modal-close" onclick="closeSignatureModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <div class="signature-modal-body" id="signatureModalBody">
            <!-- Info section -->
            <div class="signature-info">
                <div class="signature-info-row">
                    <span class="signature-info-label">เอกสาร:</span>
                    <span class="signature-info-value" id="sigDocName">สัญญาเช่าห้องพัก</span>
                </div>
                <div class="signature-info-row">
                    <span class="signature-info-label">ผู้ลงนาม:</span>
                    <span class="signature-info-value" id="sigSignerName">-</span>
                </div>
                <div class="signature-info-row">
                    <span class="signature-info-label">วันที่:</span>
                    <span class="signature-info-value" id="sigDate">-</span>
                </div>
            </div>
            
            <!-- Signature Pad -->
            <div class="signature-pad-container" id="signaturePadContainer">
                <canvas id="signaturePadCanvas" class="signature-pad-canvas"></canvas>
            </div>
            <p class="signature-pad-hint">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                    <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                </svg>
                ใช้นิ้วหรือเมาส์วาดลายเซ็นของคุณ
            </p>
            
            <!-- Action buttons -->
            <div class="signature-actions">
                <button class="signature-btn signature-btn-clear" onclick="clearSignaturePad()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                    </svg>
                    ล้างลายเซ็น
                </button>
                <button class="signature-btn signature-btn-confirm" id="signatureConfirmBtn" onclick="confirmSignature()" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                    ยืนยันลายเซ็น
                </button>
            </div>
            
            <!-- Legal notice -->
            <div class="signature-legal">
                <strong>⚠️ ข้อตกลงการลงลายมือชื่ออิเล็กทรอนิกส์</strong>
                การลงลายมือชื่อนี้มีผลผูกพันทางกฎหมายเทียบเท่าการลงนามด้วยมือ 
                ระบบจะบันทึกข้อมูลประกอบได้แก่ วันเวลา, IP Address และอุปกรณ์ที่ใช้ เพื่อใช้เป็นหลักฐาน
            </div>
        </div>
        
        <!-- Success state -->
        <div class="signature-success" id="signatureSuccess">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9 12l2 2 4-4"/>
            </svg>
            <h4>บันทึกลายเซ็นสำเร็จ!</h4>
            <p>ลายเซ็นของคุณถูกบันทึกเรียบร้อยแล้ว</p>
        </div>
        
        <div class="signature-modal-footer">
            ลายเซ็นอิเล็กทรอนิกส์มีผลตาม พ.ร.บ.ธุรกรรมทางอิเล็กทรอนิกส์ พ.ศ. 2544
        </div>
    </div>
</div>

<script>
// Signature Pad Implementation
let signaturePad = null;
let signatureData = {
    contractId: null,
    signerType: 'tenant', // tenant or owner
    signerName: '',
    documentName: 'สัญญาเช่าห้องพัก'
};

// Initialize Signature Pad
function initSignaturePad() {
    const canvas = document.getElementById('signaturePadCanvas');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    const container = document.getElementById('signaturePadContainer');
    
    // Set canvas size
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.scale(ratio, ratio);
        ctx.strokeStyle = '#1e293b';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }
    
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;
    let hasSignature = false;
    
    function getPosition(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }
    
    function startDrawing(e) {
        e.preventDefault();
        isDrawing = true;
        container.classList.add('active');
        const pos = getPosition(e);
        lastX = pos.x;
        lastY = pos.y;
    }
    
    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        
        const pos = getPosition(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        
        lastX = pos.x;
        lastY = pos.y;
        hasSignature = true;
        
        // Enable confirm button
        document.getElementById('signatureConfirmBtn').disabled = false;
    }
    
    function stopDrawing() {
        isDrawing = false;
        container.classList.remove('active');
    }
    
    // Mouse events
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    // Touch events
    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);
    
    signaturePad = {
        canvas: canvas,
        ctx: ctx,
        hasSignature: () => hasSignature,
        clear: () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSignature = false;
            document.getElementById('signatureConfirmBtn').disabled = true;
        },
        toDataURL: () => canvas.toDataURL('image/png')
    };
}

// Open signature modal
function openSignatureModal(options = {}) {
    signatureData.contractId = options.contractId || null;
    signatureData.signerType = options.signerType || 'tenant';
    signatureData.signerName = options.signerName || '';
    signatureData.documentName = options.documentName || 'สัญญาเช่าห้องพัก';
    
    // Update UI
    document.getElementById('sigDocName').textContent = signatureData.documentName;
    document.getElementById('sigSignerName').textContent = signatureData.signerName || '-';
    document.getElementById('sigDate').textContent = new Date().toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Show modal
    document.getElementById('signatureModal').classList.add('active');
    document.getElementById('signatureModalBody').style.display = 'block';
    document.getElementById('signatureSuccess').classList.remove('show');
    
    // Initialize pad
    setTimeout(() => {
        initSignaturePad();
        if (signaturePad) signaturePad.clear();
    }, 100);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

// Close signature modal
function closeSignatureModal() {
    document.getElementById('signatureModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Clear signature pad
function clearSignaturePad() {
    if (signaturePad) {
        signaturePad.clear();
    }
}

// Confirm and save signature
async function confirmSignature() {
    if (!signaturePad || !signaturePad.hasSignature()) {
        alert('กรุณาลงลายมือชื่อก่อน');
        return;
    }
    
    const btn = document.getElementById('signatureConfirmBtn');
    btn.classList.add('loading');
    btn.disabled = true;
    
    try {
        const signatureImage = signaturePad.toDataURL();
        
        const response = await fetch('/dormitory_management/Manage/save_signature.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                contract_id: signatureData.contractId,
                signer_type: signatureData.signerType,
                signer_name: signatureData.signerName,
                signature_image: signatureImage,
                user_agent: navigator.userAgent,
                screen_resolution: window.screen.width + 'x' + window.screen.height
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success
            document.getElementById('signatureModalBody').style.display = 'none';
            document.getElementById('signatureSuccess').classList.add('show');
            
            // Reload page after delay
            setTimeout(() => {
                closeSignatureModal();
                location.reload();
            }, 2000);
        } else {
            throw new Error(result.error || 'ไม่สามารถบันทึกลายเซ็นได้');
        }
    } catch (error) {
        console.error('Signature save error:', error);
        alert('เกิดข้อผิดพลาด: ' + error.message);
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

// Close on overlay click
document.getElementById('signatureModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeSignatureModal();
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSignatureModal();
    }
});
</script>
