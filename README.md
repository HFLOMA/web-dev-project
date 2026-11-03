# PaceUp

เว็บติดตามการวิ่ง เช็คอินด้วยรูปผลวิ่ง เก็บ Streak และซื้อไอเทมกัน Streak ขาด (Web Development 1/69)

## โครงสร้างไฟล์ (Project Structure)

```text
web-dev-project/
│
│   ── หน้าหลัก + เช็คอิน ──
├── index.php                      # หน้าหลัก: Streak, ระยะทางสัปดาห์นี้, อันดับ, เป้าหมาย
├── checkin.php                    # หน้าเช็คอิน: อัปรูป Garmin → อ่านระยะด้วย OCR → บันทึก (Ajax + JSON)
├── header.php                     # แถบบนใช้ร่วม: โลโก้, จำนวนไอเทม, ร้านค้า, เมนู ☰
├── functions.php                  # ฟังก์ชันใช้ร่วม: e(), get_user(), check_streak(), get_goal()
├── connect.php                    # เชื่อมฐานข้อมูลของหน้าหลัก/เช็คอิน (ไม่อยู่ใน Git ต้องสร้างเอง)
├── connect.example.php            # ตัวอย่าง connect.php (ไม่มีรหัสผ่าน)
├── css/
│   └── home.css                   # CSS หน้าหลัก + เช็คอิน (Mobile First)
├── js/
│   └── scan.js                    # อ่านระยะทาง/เวลาจากรูป Garmin Connect (ใช้คู่กับ Tesseract.js)
├── img/
│   └── example.jpg                # รูปตัวอย่างในหน้าเช็คอิน
├── uploads/                       # รูปที่ผู้ใช้อัปตอนเช็คอิน (รูปไม่ขึ้น Git)
│
│   ── ร้านค้า + ประวัติธุรกรรม ──
├── shop.php                       # หน้าร้านค้าไอเทม (Shop View)
├── history.php                    # หน้าประวัติธุรกรรม & สถิติเชิงลึก (History View)
├── logout.php                     # สคริปต์เคลียร์ Session และออกจากระบบ
├── index1.php                     # ไฟล์ทดสอบ
├── api/                           # โฟลเดอร์รวม Backend API endpoints สำหรับรับ-ส่ง Ajax
│   ├── api_buy.php                # API ประมวลผลคำสั่งซื้อไอเทม (Database Transaction)
│   ├── api_topup.php              # API ประมวลผลการเติมเงินจำลอง
│   └── api_history.php            # API สรุปสถิติ กราฟ และประวัติธุรกรรม (Pagination)
├── assets/                        # โฟลเดอร์รวมไฟล์ Static ทั้งหมดของหน้าเว็บ
│   ├── css/
│   │   └── style.css              # สไตล์ชีทโมเดิร์น (Glassmorphism Navbar + PC Grid)
│   └── js/
│       ├── shop.js                # สคริปต์ควบคุมหน้าร้านค้าและ Ajax Popup
│       └── history.js             # สคริปต์ควบคุมหน้าสถิติ กราฟ Chart.js และตาราง
├── config/
│   └── connect.php                # เชื่อมฐานข้อมูล PDO + Session ของหน้าร้านค้า (ไม่อยู่ใน Git ต้องสร้างเอง)
│
│   ── ใช้ร่วมกัน ──
├── database/                      # สคริปต์ SQL (Import ใน phpMyAdmin)
│   ├── supabase-to-kmitl.sql      # สร้างตาราง paceup__* และ view (ทำก่อน)
│   └── supabase-data-to-kmitl.sql # ข้อมูลตัวอย่าง (ทำทีหลัง)
├── docs/
│   └── goal-formula-references.md # ที่มาของสูตรเป้าวิ่งรายสัปดาห์
├── .gitignore                     # ไฟล์ที่ไม่ขึ้น Git (connect.php, รูปใน uploads/)
└── README.md                      # เอกสารสรุปรายละเอียดโปรเจกต์
```

## วิธีรันในเครื่อง

1. เปิด MySQL ใน XAMPP สร้างฐานข้อมูล `169DB_15` และ user `169Muser15`
2. phpMyAdmin → เลือก `169DB_15` → Import `database/supabase-to-kmitl.sql` แล้วตามด้วย `database/supabase-data-to-kmitl.sql`
3. ก๊อป `connect.example.php` เป็น `connect.php` แล้วใส่รหัสผ่าน และสร้าง `config/connect.php` สำหรับหน้าร้านค้า
4. เปิด terminal ในโฟลเดอร์นี้ พิมพ์ `php -S localhost:8001` แล้วเปิด http://localhost:8001/index.php

> `connect.php` และ `config/connect.php` มีรหัสผ่านฐานข้อมูล ห้ามขึ้น Git (อยู่ใน `.gitignore` แล้ว)
