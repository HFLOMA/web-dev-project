-- ตั้งรหัสผ่านทดสอบให้ user ตัวอย่าง (รหัสเดิมเป็นค่าปลอม login ไม่ได้)
-- รหัสผ่านทุกคน = Test1234
-- วิธีใช้ phpMyAdmin > 169DB_15 > Import > เลือกไฟล์นี้ > Go
UPDATE paceup__users SET pass = '$2y$10$Sj0JQdpBOKTjMhovSMVnIeVW8uN/tT/M7wglr.4PQitFDP.la8z.y' WHERE id IN (1, 2, 3);
