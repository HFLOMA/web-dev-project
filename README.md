(Project Structure)

```text
RunTracking/
│
├── api/                           # โฟลเดอร์รวม Backend API endpoints สำหรับรับ-ส่ง Ajax
│   ├── api_buy.php                # API ประมวลผลคำสั่งซื้อไอเทม (Database Transaction)
│   ├── api_topup.php              # API ประมวลผลการเติมเงินจำลอง
│   └── api_history.php            # API สรุปสถิติ กราฟ และประวัติธุรกรรม (Pagination)
│
├── assets/                        # โฟลเดอร์รวมไฟล์ Static ทั้งหมดของหน้าเว็บ
│   ├── css/
│   │   └── style.css              # สไตล์ชีทโมเดิร์น (Glassmorphism Navbar + PC Grid)
│   └── js/
│       ├── shop.js                # สคริปต์ควบคุมหน้าร้านค้าและ Ajax Popup
│       └── history.js             # สคริปต์ควบคุมหน้าสถิติ กราฟ Chart.js และตาราง
│
├── config/                        # โฟลเดอร์ตั้งค่าระบบ
│   └── connect.php                # ไฟล์เชื่อมต่อฐานข้อมูล PDO (ฐานข้อมูล paceup) + Session
│
├── sql/                           # โฟลเดอร์สคริปต์ฐานข้อมูล
│   ├── paceup_database.sql        # สคริปต์ SQL สร้าง Database paceup พร้อมตาราง paceup__*
│   └── database.sql               # สำเนาไฟล์ SQL สำหรับความสะดวกในการนำเข้า
│
├── shop.php                       # หน้าร้านค้าไอเทม (Shop View)
├── history.php                    # หน้าประวัติธุรกรรม & สถิติเชิงลึก (History View)
├── logout.php                     # สคริปต์เคลียร์ Session และออกจากระบบ
├── index.php                      # หน้าแรก ตั้งเป็นหน้า Home
└── README.md                      # เอกสารสรุปรายละเอียดโปรเจกต์
``