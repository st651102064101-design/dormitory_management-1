--------------------------------------------------------------------------------
1. การตั้งค่าฟอนต์ (Typography)
Apple ใช้ฟอนต์ San Francisco (SF Pro) เป็นหลัก ซึ่งในเว็บไซต์เราสามารถเรียกใช้ฟอนต์ระบบของ Apple ได้โดยไม่ต้องโหลดไฟล์ฟอนต์เข้าไปใหม่ ด้วยคำสั่ง CSS นี้
:
font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", sans-serif;
หลักการตั้งค่าขนาด (Size), น้ำหนัก (Weight) และระยะห่างตัวอักษร (Letter Spacing): Apple จะแยกใช้ฟอนต์แบบ Display (สำหรับตัวอักษร 20px ขึ้นไป) และ Text (19px ลงมา) พร้อมการตั้งค่า Line-height ประมาณ 120%-130%
:
ประเภทข้อความ
ขนาด (Font Size)
น้ำหนัก (Weight)
ระยะห่างอักษร (Letter Spacing)
Line Height
Large Title / Hero
34px
Bold (700)
-1.05px
41px (หรือ ~1.2)
Title 1 (H1)
28px
Bold (700)
-0.8px
34px (หรือ ~1.2)
Title 2 (H2)
22px
Bold (700)
-0.7px
28px (หรือ ~1.2)
Headline (H3)
17px
Semi-Bold (600)
-0.43px
22px (หรือ ~1.3)
Body (เนื้อหาหลัก)
17px
Regular (400)
-0.43px
22px (หรือ ~1.3)
Callout / Secondary
16px
Regular (400)
-0.32px
20px (หรือ ~1.3)
Caption (ตัวเล็กสุด)
12px
Regular (400)
+0.12px
15px (หรือ ~1.3)
(อ้างอิงจากตัวเลข Dynamic Type ของ Apple HIG
)

--------------------------------------------------------------------------------
2. ชุดสี (Color Palette)
เว็บไซต์ของ Apple ใช้สีที่น้อยมาก (Minimal Colors) มักจะเน้นที่ขาว ดำ และเทา โดยมีสีหลัก (Accent Color) คือสีน้ำเงิน
 หากรองรับ Dark Mode ควรตั้งค่า CSS Variables ดังนี้
:
:root {
  /* Light Mode */
  --bg-primary: #FFFFFF;
  --bg-secondary: #F2F2F7; /* สำหรับพื้นหลัง Section หรือ Card */
  --text-primary: #000000;
  --text-secondary: rgba(60, 60, 67, 0.6); /* สีเทาสำหรับข้อความรอง */
  --accent-blue: #007AFF;
  --system-red: #FF3B30;
  --separator: rgba(60, 60, 67, 0.3); /* เส้นแบ่งจางๆ */
}

@media (prefers-color-scheme: dark) {
  :root {
    /* Dark Mode */
    --bg-primary: #000000;
    --bg-secondary: #1C1C1E; 
    --text-primary: #FFFFFF;
    --text-secondary: rgba(235, 235, 245, 0.6);
    --accent-blue: #0A84FF;
    --system-red: #FF453A;
    --separator: rgba(84, 84, 88, 0.3);
  }
}

--------------------------------------------------------------------------------
3. เอฟเฟกต์โปร่งแสง (Glassmorphism / Liquid Glass)
นี่คือเอกลักษณ์ที่โดดเด่นที่สุดของ Apple ใช้สำหรับ Navigation Bar ด้านบน, Dropdown Menu, หรือ Modal พื้นหลังจะต้องมีความเบลอและดึงสีพื้นหลังขึ้นมา
CSS สำหรับสร้างเอฟเฟกต์แบบ Apple (Navigation Bar หรือ Panel):
.apple-glass {
  background-color: rgba(255, 255, 255, 0.5); /* สีขาวโปร่งแสง (Light mode) */
  backdrop-filter: blur(33px); /* ค่าความเบลอที่พอดีกับ Apple Style */
  -webkit-backdrop-filter: blur(33px); /* รองรับ Safari */
  border-bottom: 1px solid rgba(0, 0, 0, 0.1); /* ขอบล่างจางๆ */
}
(ใน Dark mode ให้เปลี่ยน background-color เป็นสีดำโปร่งแสง เช่น rgba(0, 0, 0, 0.5) และเส้นขอบเป็นสีขาวจางๆ)

--------------------------------------------------------------------------------
4. การจัดวางและระยะห่าง (Layout & Spacing)
Grid Base: Apple มักใช้ระบบ Grid ที่มีพื้นฐานการเว้นระยะด้วยค่าที่หารด้วย 8 ลงตัว (8-Point Grid System) เช่น 8px, 16px, 24px, 32px
Bento Grid: รูปแบบการจัด Layout ยอดฮิตที่ Apple ชอบใช้โปรโมตฟีเจอร์คือ Bento Box (กล่องสี่เหลี่ยมแบ่งช่องเหมือนกล่องข้าวเบนโตะ) เทคนิคคือการใช้ CSS Grid วางกล่องที่มีขนาดต่างกัน (เล็ก, กลาง, ใหญ่) รวมกลุ่มเข้าด้วยกัน
White Space: ปล่อยพื้นที่ว่างให้มากที่สุด ไม่ยัดเยียดเนื้อหา ให้ตัวอักษรและรูปภาพเด่นด้วยตัวมันเอง

--------------------------------------------------------------------------------
5. ความโค้งมน (Border Radius) และเงา (Shadow)
ค่าความโค้งมนต้องเข้ากันได้ดีกับขนาดของ Layout ไม่ควรโค้งมากหรือน้อยเกินไป:
การ์ด (Cards) / กล่อง Bento Grid: มักใช้ค่ารัศมีใหญ่ เช่น border-radius: 18px ถึง 24px ขึ้นอยู่กับขนาดของกล่อง
ปุ่ม (Buttons) / กล่อง Alert: ตามมาตรฐาน iOS จะใช้ที่ border-radius: 10px, 12px หรือ 13px
เงา (Box Shadow): Apple ไม่ค่อยใช้เงาเข้มๆ แต่จะใช้เงาที่กว้าง นุ่มนวล และบางมากๆ เพื่อสร้างมิติของความลึก
 เช่น:

--------------------------------------------------------------------------------
6. การนำทาง (Navigation) & รูปภาพ (Graphics)
ภาพกราฟิกต้องคุณภาพสูงสุด (High-Quality Graphics): ไม่ใช้ภาพ Stock ถ่ายสินค้าแบบหน้าตรงหรือมุมเอียงบนพื้นหลังสีขาวหรือสีล้วน (Minimalist Product Photography) และเว้นพื้นที่รอบภาพให้เยอะ
การใส่ภาพใน Bento Grid: หากมีกราฟิกภายในกล่อง ให้ใช้รูปภาพเต็มพื้นที่ขอบกล่อง (Edge-to-edge) แล้วตั้งค่า overflow: hidden; ที่ตัวกล่องหลัก
แถบนำทาง (Nav Bar): นำทางด้วยเมนูแนวนอนที่สะอาดตา และไม่ควรมีเมนูย่อยแบบ Drop-down ที่ซับซ้อน หรือถ้ามีควรทำเป็น Mega-menu ที่ทำฉากหลังให้เป็นสีเบลอ