1. Typography (ระบบตัวอักษร)
Apple ใช้ฟอนต์ San Francisco (SF Pro) โดยเว็บไซต์สามารถดึงฟอนต์ระบบของ Apple มาใช้ได้โดยตรงผ่าน CSS
 Apple จะมีการกำหนดค่า Tracking (ระยะห่างตัวอักษร) ตามขนาดฟอนต์เสมอ
CSS Setup:
:root {
  /* ระบบฟอนต์ดึงจาก OS ของผู้ใช้ */
  --font-apple: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", sans-serif;
}

body {
  font-family: var(--font-apple);
  -webkit-font-smoothing: antialiased; /* ทำให้ฟอนต์ดูเนียนตาแบบ Apple */
  -moz-osx-font-smoothing: grayscale;
}
ตารางค่าขนาดอักษร (อ้างอิงจาก iOS/macOS Default)
:
ระดับ (Hierarchy)
ขนาด (Size)
น้ำหนัก (Weight)
Letter-spacing
Large Title (หัวข้อหลักหน้าเว็บ)
34px
Bold (700) หรือ Light (300)
0.40px
Title 1 (H1)
28px
Bold (700)
0.36px
Title 2 (H2)
22px
Bold / Regular
0.35px
Headline (หัวข้อรอง)
17px
Semi-bold (600)
-0.43px
Body (เนื้อหาทั่วไป)
17px
Regular (400)
-0.43px
Callout (คำอธิบายรอง)
16px
Regular (400)
-0.32px
Caption 1 (ข้อความขนาดเล็ก)
12px
Regular (400)
0px

--------------------------------------------------------------------------------
2. ชุดสี (Color System: Light & Dark Mode)
การออกแบบสไตล์ Apple จะเน้นพื้นหลังที่สะอาดตาและใช้ Semantic Colors (สีที่เปลี่ยนตามโหมดมืด/สว่างอัตโนมัติ)
CSS Variables สำหรับนำไปใช้:
:root {
  /* Light Mode Colors */
  --bg-primary: #FFFFFF;
  --bg-secondary: #F2F2F7; /* สำหรับการ์ด หรือพื้นหลัง Section [3] */
  
  --text-primary: #000000;
  --text-secondary: rgba(60, 60, 67, 0.6); /* สีเทาสำหรับ Text รอง [10] */
  --text-tertiary: rgba(60, 60, 67, 0.3);
  
  --system-blue: #007AFF; /* สีปุ่มและลิงก์ [11] */
  --system-red: #FF3B30; /* สีแจ้งเตือน/ลบ [3] */
  
  --separator: rgba(60, 60, 67, 0.3); /* สีเส้นคั่น [10] */
}

@media (prefers-color-scheme: dark) {
  :root {
    /* Dark Mode Colors */
    --bg-primary: #000000; /* [10] */
    --bg-secondary: #1C1C1E; /* [3] */
    
    --text-primary: #FFFFFF; /* [10] */
    --text-secondary: rgba(235, 235, 245, 0.6); /* [10] */
    --text-tertiary: rgba(235, 235, 245, 0.3);
    
    --system-blue: #0A84FF; /* สว่างขึ้นเพื่อ Contrast ที่ดี [11] */
    --system-red: #FF453A; /* [3] */
    
    --separator: rgba(84, 84, 88, 0.3); /* [10] */
  }
}

--------------------------------------------------------------------------------
3. เอฟเฟกต์โปร่งแสง "Liquid Glass" (Glassmorphism)
Apple ใช้เอฟเฟกต์เบลอใน Navigation Bar, Modal และ Widget อย่างแพร่หลาย หัวใจสำคัญคือการใช้ backdrop-filter ร่วมกับการปรับแสงขอบ (Inner Border)
CSS สำหรับสร้าง Liquid Glass แบบ Apple 100%:
.apple-glass {
  /* 1. สีพื้นหลังแบบโปร่งแสง */
  background-color: rgba(255, 255, 255, 0.65); 
  
  /* 2. การเบลอฉากหลัง (ค่า 20px - 33px คือค่าที่ Apple นิยม) [14] */
  backdrop-filter: blur(33px);
  -webkit-backdrop-filter: blur(33px); /* รองรับ Safari */
  
  /* 3. การทำขอบสะท้อนแสง (Specular rim/Inner Glow) ให้ดูเป็นกระจกจริงๆ [13] */
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
}

/* สำหรับ Dark Mode */
@media (prefers-color-scheme: dark) {
  .apple-glass {
    background-color: rgba(30, 30, 30, 0.65);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.1);
  }
}

--------------------------------------------------------------------------------
4. Layout, Spacing & Bento Grid (ระบบการจัดวาง)
Apple ใช้การจัดวางที่มีตรรกะชัดเจนและ "พื้นที่ว่าง" จำนวนมาก (Minimalism)
Grid System (ระบบ Grid): Apple นิยมใช้ระยะห่างที่อิงกับ 8pt/8px (เช่น 8px, 16px, 24px, 32px)
Bento Grid: รูปแบบการ์ดตารางที่ใช้โชว์ฟีเจอร์สินค้า
CSS สำหรับทำ Bento Grid เบื้องต้น:
.bento-container {
  display: grid;
  gap: 16px; /* ระยะห่างมาตรฐาน [17] */
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  padding: 24px;
}

.bento-card {
  background-color: var(--bg-secondary);
  border-radius: 18px; /* หรือ 24px สำหรับการ์ดขนาดใหญ่ */
  padding: 24px;
  overflow: hidden; /* เผื่อกรณีมีรูปภาพชิดขอบ */
}

--------------------------------------------------------------------------------
5. Buttons & Controls (ปุ่มและการโต้ตอบ)
กฎเหล็กในการสร้างปุ่มให้เหมือน Apple คือ "พื้นที่กดต้องกว้างพอ" และ "ขอบมนอย่างเป็นธรรมชาติ"
ข้อกำหนด:
พื้นที่ขั้นต่ำ (Minimum Touch Target): กว้างและสูงไม่ต่ำกว่า 44px เสมอ
Corner Radius (ความโค้งมน): ปุ่มเล็กมักใช้ 8px ถึง 12px ปุ่มขนาดใหญ่มักเป็นแบบ Capsule (รัศมีโค้งสุด border-radius: 999px)
CSS สำหรับปุ่ม (Primary Button):
.apple-button {
  min-width: 44px;
  min-height: 44px; /* พื้นที่ทัชขั้นต่ำ [21] */
  padding: 12px 24px;
  
  background-color: var(--system-blue);
  color: #FFFFFF;
  
  font-family: var(--font-apple);
  font-size: 17px;
  font-weight: 600; /* Semi-bold [23] */
  letter-spacing: -0.43px;
  
  border: none;
  border-radius: 12px; /* หรือ 999px สำหรับ Capsule */
  cursor: pointer;
  transition: opacity 0.2s ease;
}

/* เอฟเฟกต์เมื่อกด (Press State) จำเป็นต้องมี [24] */
.apple-button:active {
  opacity: 0.7; /* iOS มักจะเฟดปุ่มเวลาถูกกด */
}
สรุปหลักการนำไปใช้ (Key Takeaways):
ห้ามใช้ฟอนต์อื่น หากต้องการสไตล์ Apple 100% ให้ปล่อยเป็น -apple-system ระบบจะจัดการดึงฟอนต์ San Francisco มาให้เอง
เงา (Shadows) Apple ในยุคใหม่แทบไม่ใช้ Drop Shadow เข้มๆ เลย แต่จะใช้เงาฟุ้งๆ บางมากๆ เช่น box-shadow: 0 10px 30px rgba(0,0,0,0.05); เพื่อแยกเลเยอร์
ไม่มีเส้นขอบทึบๆ (No Solid Borders) ถ้ามีเส้นคั่น ควรเป็นสีเทาที่โปร่งแสง 30% (rgba(60,60,67,0.3)) เพื่อให้กลืนไปกับพื้นหลัง
ใช้ Liquid Glass อย่างระมัดระวัง วางเอฟเฟกต์เบลอไว้เฉพาะจุดที่ลอยอยู่บนเนื้อหาอื่นๆ เช่น Navbar ด้านบน หรือ Panel แจ้งเตือน