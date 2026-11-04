-- สร้างตารางของ PaceUp ใน DB มหาลัย 169DB_15 ย้ายมาจาก Supabase
-- ทุกตารางขึ้นต้นด้วย paceup__ จะได้ไม่ชนกับตารางของแล็บ phpMyAdmin รวมเป็นโฟลเดอร์ paceup ให้
-- วิธีใช้ phpMyAdmin > 169DB_15 > Import > เลือกไฟล์นี้ > Go (ทำก่อนไฟล์ data)
-- รันซ้ำได้ แต่จะลบตาราง paceup__ ของเก่าทิ้งแล้วสร้างใหม่ ข้อมูลหายหมด

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ลบของเก่าก่อน pu_ คือชื่อเก่า paceup__ คือชื่อปัจจุบัน
DROP VIEW IF EXISTS pu_view_runner_stats, pu_view_item_sales_summary, pu_view_order_details;
DROP TABLE IF EXISTS pu_item_usages, pu_order_items, pu_check_ins, pu_orders, pu_items, pu_users;

DROP VIEW IF EXISTS paceup__view_runner_stats, paceup__view_item_sales_summary, paceup__view_order_details;
DROP TABLE IF EXISTS paceup__item_usages, paceup__order_items, paceup__check_ins, paceup__orders, paceup__items, paceup__users;

SET FOREIGN_KEY_CHECKS = 1;

-- สมาชิก เก็บ streak , ไอเทมที่มี , น้ำหนัก ส่วนสูง ไว้คิดเป้า
CREATE TABLE paceup__users (
  id                BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'รหัสผู้ใช้',
  name              VARCHAR(255) NULL COMMENT 'ชื่อที่แสดง',
  email             VARCHAR(255) NULL UNIQUE COMMENT 'อีเมล (ใช้ล็อกอิน ห้ามซ้ำ)',
  pass              VARCHAR(255) NULL COMMENT 'รหัสผ่านที่ผ่าน password_hash() แล้ว',
  role              VARCHAR(50)  NULL DEFAULT 'user' COMMENT 'สิทธิ์: user / admin',
  gender            VARCHAR(50)  NULL COMMENT 'เพศ: male / female / other',
  birthdate         DATE NULL COMMENT 'วันเกิด',
  weight            DECIMAL(10,2) NULL COMMENT 'น้ำหนัก (กก.)',
  height            DECIMAL(10,2) NULL COMMENT 'ส่วนสูง (ซม.)',
  target_distance   DECIMAL(10,2) NULL COMMENT 'เป้าระยะวิ่งต่อสัปดาห์ (กม.)',
  current_streak    INT NULL DEFAULT 0 COMMENT 'จำนวนวันที่วิ่งต่อเนื่องตอนนี้',
  last_checkin_date DATE NULL COMMENT 'วันที่เช็คอินล่าสุด',
  last_freeze_date  DATE NULL COMMENT 'วันที่ใช้ไอเทม Freeze ล่าสุด',
  shield_balance    INT NULL DEFAULT 0 COMMENT 'จำนวนโล่กันสตรีคขาดที่มี',
  freeze_balance    INT NULL DEFAULT 0 COMMENT 'จำนวนไอเทม Freeze ที่มี',
  insurance_balance INT NULL DEFAULT 0 COMMENT 'จำนวนไอเทม Insurance ที่มี',
  created_at        DATETIME NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สมัคร',
  experience        VARCHAR(255) NULL COMMENT 'ประสบการณ์วิ่ง: beginner / regular / serious'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='สมาชิก (นักวิ่ง)';

-- ไอเทมในร้าน Shield , Repair , Insurance
CREATE TABLE paceup__items (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'รหัสไอเทม',
  name        VARCHAR(255) NOT NULL COMMENT 'ชื่อไอเทม',
  description TEXT NULL COMMENT 'คำอธิบายไอเทม',
  price       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ราคา',
  max_limit   INT NULL COMMENT 'ซื้อได้สูงสุดกี่ชิ้น (ว่าง = ไม่จำกัด)',
  is_active   TINYINT(1) NULL DEFAULT 1 COMMENT '1 = วางขายอยู่, 0 = ปิดขาย',
  created_at  DATETIME NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่เพิ่มไอเทม'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ไอเทมในร้านค้า';

-- คำสั่งซื้อ 1 แถว = กดซื้อ 1 ครั้ง
CREATE TABLE paceup__orders (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'รหัสคำสั่งซื้อ',
  user_id     BIGINT NOT NULL COMMENT 'ผู้ซื้อ (paceup__users.id)',
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ยอดรวมทั้งออเดอร์',
  status      VARCHAR(50) NULL DEFAULT 'pending' COMMENT 'สถานะ: pending / completed / cancelled',
  created_at  DATETIME NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'วันที่สั่งซื้อ',
  FOREIGN KEY (user_id) REFERENCES paceup__users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='คำสั่งซื้อ (1 แถว = กดซื้อ 1 ครั้ง)';

-- ของในคำสั่งซื้อ 1 คำสั่งมีได้หลายชิ้น
CREATE TABLE paceup__order_items (
  id       BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'รหัสรายการ',
  order_id BIGINT NOT NULL COMMENT 'อยู่ในคำสั่งซื้อไหน (paceup__orders.id)',
  item_id  BIGINT NOT NULL COMMENT 'ไอเทมที่ซื้อ (paceup__items.id)',
  qty      INT NOT NULL DEFAULT 1 COMMENT 'จำนวนชิ้น',
  FOREIGN KEY (order_id) REFERENCES paceup__orders(id),
  FOREIGN KEY (item_id)  REFERENCES paceup__items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='สินค้าแต่ละชิ้นในคำสั่งซื้อ';

-- ประวัติใช้ไอเทม เช่น ใช้ Shield อัตโนมัติตอนขาดวิ่ง
CREATE TABLE paceup__item_usages (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'รหัสการใช้',
  user_id    BIGINT NOT NULL COMMENT 'ผู้ใช้ไอเทม (paceup__users.id)',
  item_id    BIGINT NOT NULL COMMENT 'ไอเทมที่ใช้ (paceup__items.id)',
  usage_type VARCHAR(100) NULL COMMENT 'วิธีใช้: auto (ระบบใช้ให้) / manual (กดใช้เอง)',
  used_at    DATETIME NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาที่ใช้',
  FOREIGN KEY (user_id) REFERENCES paceup__users(id),
  FOREIGN KEY (item_id) REFERENCES paceup__items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ประวัติการใช้ไอเทม';

-- เช็คอินวิ่ง image_hash ห้ามซ้ำ กันส่งรูปเดิม
CREATE TABLE paceup__check_ins (
  id                  BIGINT AUTO_INCREMENT PRIMARY KEY COMMENT 'รหัสการเช็คอิน',
  user_id             BIGINT NOT NULL COMMENT 'ผู้เช็คอิน (paceup__users.id)',
  image_url           VARCHAR(1024) NULL COMMENT 'ที่อยู่รูปผลวิ่ง',
  ocr_distance        DECIMAL(10,2) NULL COMMENT 'ระยะที่ระบบอ่านได้จากรูป (กม.)',
  user_input_distance DECIMAL(10,2) NULL COMMENT 'ระยะที่ผู้ใช้กรอกเอง (กม.)',
  duration            INT NULL COMMENT 'เวลาวิ่ง (วินาที)',
  status              VARCHAR(50) NULL DEFAULT 'pending' COMMENT 'สถานะ: pending / approved / rejected',
  created_at          DATETIME NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาเช็คอิน',
  image_hash          VARCHAR(255) NULL UNIQUE COMMENT 'ลายนิ้วมือของรูป กันส่งรูปซ้ำ',
  FOREIGN KEY (user_id) REFERENCES paceup__users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='การเช็คอินวิ่งแต่ละครั้ง';

-- view อันดับ รวมระยะที่ approved ของแต่ละคน เรียงมากไปน้อย ใช้ในหน้าแรก
CREATE VIEW paceup__view_runner_stats AS
SELECT u.name                        AS `ชื่อผู้ใช้`,
       COUNT(c.id)                   AS `จำนวนครั้งที่วิ่ง`,
       SUM(c.ocr_distance)           AS `ระยะทางรวม`,
       ROUND(AVG(c.ocr_distance), 2) AS `ระยะทางเฉลี่ยต่อครั้ง`
FROM paceup__users u
JOIN paceup__check_ins c ON u.id = c.user_id
WHERE c.status = 'approved'
GROUP BY u.name
ORDER BY SUM(c.ocr_distance) DESC;

-- view ยอดขายไอเทม เฉพาะคำสั่งซื้อที่ completed
CREATE VIEW paceup__view_item_sales_summary AS
SELECT i.name                AS `ชื่อสินค้า`,
       SUM(oi.qty)           AS `จำนวนชิ้นที่ขายได้`,
       SUM(oi.qty * i.price) AS `ยอดเงินรวมทั้งหมด`
FROM paceup__items i
JOIN paceup__order_items oi ON i.id = oi.item_id
JOIN paceup__orders o       ON oi.order_id = o.id
WHERE o.status = 'completed'
GROUP BY i.name
ORDER BY SUM(oi.qty * i.price) DESC;

-- view รายละเอียดคำสั่งซื้อ ใคร ซื้ออะไร กี่ชิ้น เท่าไหร่
CREATE VIEW paceup__view_order_details AS
SELECT u.name           AS `ชื่อลูกค้า`,
       o.created_at     AS `วันที่ซื้อ`,
       i.name           AS `ชื่อสินค้า`,
       oi.qty           AS `จำนวน`,
       oi.qty * i.price AS `ยอดรวม (บาท)`
FROM paceup__orders o
JOIN paceup__users u        ON o.user_id = u.id
JOIN paceup__order_items oi ON o.id = oi.order_id
JOIN paceup__items i        ON oi.item_id = i.id
ORDER BY o.created_at DESC;