-- ใส่ข้อมูลตัวอย่าง ย้ายมาจาก Supabase รันหลัง supabase-to-kmitl.sql
-- รันซ้ำได้ แต่จะลบข้อมูล paceup__ ทั้งหมดก่อน เช็คอินที่ทำไว้หายหมด
-- เวลาบวก 7 ชม. เป็นเวลาไทยแล้ว
-- รหัสผ่านเป็นค่าตัวอย่าง ไม่ใช่ hash จริง ล็อกอินด้วยบัญชีพวกนี้ไม่ได้

SET NAMES utf8mb4;
-- ลบข้อมูลเก่า ปิดเช็ค foreign key ก่อน ไม่งั้นลบไม่ได้
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM paceup__item_usages;
DELETE FROM paceup__order_items;
DELETE FROM paceup__check_ins;
DELETE FROM paceup__orders;
DELETE FROM paceup__items;
DELETE FROM paceup__users;
SET FOREIGN_KEY_CHECKS = 1;

-- ผู้ใช้ 3 คน สมชาย (id 1 ใช้ทดสอบหน้าแรก) , วิภาดา , แอดมิน
INSERT INTO paceup__users
  (id, name, email, pass, role, gender, birthdate, weight, height, target_distance,
   current_streak, last_checkin_date, last_freeze_date, shield_balance, freeze_balance, created_at, experience)
VALUES
  (1, 'สมชาย สายวิ่ง',   'somchai@runner.com', '$2a$12$hashedpass1', 'user',  'male',   '1995-04-12', 68.50, 175.00,  50.00,  1, '2026-10-17', NULL,         2, 1, '2026-10-01 21:53:21', 'serious'),
  (2, 'วิภาดา รักสุขภาพ', 'wipada@runner.com',  '$2a$12$hashedpass2', 'user',  'female', '1998-08-23', 52.00, 162.00,  30.00, 14, '2026-10-01', '2026-09-25', 0, 2, '2026-10-01 21:53:21', 'regular'),
  (3, 'แอดมิน โค้ชเก่ง',  'coach@runner.com',   '$2a$12$hashedpass3', 'admin', 'male',   '1990-01-15', 74.00, 180.00, 100.00, 30, '2026-10-01', NULL,         5, 5, '2026-10-01 21:53:21', 'serious');

-- ไอเทม 3 ชนิด
INSERT INTO paceup__items (id, name, description, price, max_limit, is_active, created_at) VALUES
  (1, 'Streak Shield',            'โล่ป้องกันสถิติการวิ่งหลุด 1 วันอัตโนมัติเมื่อลืมเช็คอิน',              59.00, 5, 1, '2026-10-01 21:53:21'),
  (2, 'Streak Repair',            'ใช้กู้คืน Streak ที่หลุดไปแล้วไม่เกิน 24-48 ชั่วโมง',                    29.00, 3, 1, '2026-10-01 21:53:21'),
  (3, 'Streak Halving Insurance', 'สะสมมา 40 วัน ขาดส่งรูป แทนที่จะกลายเป็น 0 ก็จะถูกเซฟไว้ที่ 20 วันทันที', 99.00, 2, 1, '2026-10-01 21:53:21');

-- คำสั่งซื้อตัวอย่าง
INSERT INTO paceup__orders (id, user_id, total_price, status, created_at) VALUES
  (1, 1, 147.00, 'completed', '2026-09-20 10:30:00'),
  (2, 2,  58.00, 'completed', '2026-09-22 14:15:00'),
  (3, 1,  99.00, 'pending',   '2026-10-01 09:00:00');

-- ของในแต่ละคำสั่งซื้อ
INSERT INTO paceup__order_items (id, order_id, item_id, qty) VALUES
  (1, 1, 1, 2),
  (2, 1, 2, 1),
  (3, 2, 2, 2),
  (4, 3, 3, 1);

-- ประวัติใช้ไอเทม
INSERT INTO paceup__item_usages (id, user_id, item_id, usage_type, used_at) VALUES
  (1, 1, 1, 'auto_protect_streak', '2026-09-28 23:59:59'),
  (2, 2, 2, 'manual_freeze_day',   '2026-09-25 08:00:00');

-- เช็คอินตัวอย่าง id 5 คือรูป Garmin ใน uploads/
INSERT INTO paceup__check_ins
  (id, user_id, image_url, ocr_distance, user_input_distance, duration, status, created_at, image_hash)
VALUES
  (1, 1, 'https://storage.runner.com/proofs/run_user1_001.jpg', 5.25, 5.20, 1850, 'approved', '2026-09-29 18:30:00', '6a78984832ba9ef6339445ba65aff09bc1b18139b19cba4f3d383d879f12a6e4'),
  (2, 1, 'https://storage.runner.com/proofs/run_user1_002.jpg', 10.02, 10.00, 3600, 'approved', '2026-09-30 06:45:00', '1db5c03eead970f14d855fa85659ba08c69da6fe6b5a96451e27ca7a127a4190'),
  (3, 2, 'https://storage.runner.com/proofs/run_user2_001.jpg', 3.10, 3.10, 1200, 'approved', '2026-10-01 07:10:00', 'd4658f0f3f5fd5f3745a9976f0e6e7f6a241193ed47c77fda51cc24d386f12db'),
  (4, 2, 'https://storage.runner.com/proofs/run_user2_002.jpg', 6.50, 7.00, 2400, 'pending', '2026-10-01 19:20:00', '3e34e33978b5f80b8074e4ef4b2ec638e85b6dfb0c5a08c1d6b7d627079040f3'),
  (5, 1, 'uploads/bc0362ada56caf701c6413acc5e3afc83eae8879984587bf06769f1ff2eda541.jpg', 6.51, NULL, 2743, 'approved', '2026-10-17 01:09:59', 'bc0362ada56caf701c6413acc5e3afc83eae8879984587bf06769f1ff2eda541');

-- ให้ id ถัดไปต่อจากข้อมูลตัวอย่าง
ALTER TABLE paceup__users       AUTO_INCREMENT = 4;
ALTER TABLE paceup__items       AUTO_INCREMENT = 4;
ALTER TABLE paceup__orders      AUTO_INCREMENT = 4;
ALTER TABLE paceup__order_items AUTO_INCREMENT = 5;
ALTER TABLE paceup__item_usages AUTO_INCREMENT = 3;
ALTER TABLE paceup__check_ins   AUTO_INCREMENT = 6;
