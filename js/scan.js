// อ่านระยะทาง กับ เวลา จากรูป Garmin Connect ใช้คู่กับ Tesseract.js ใน checkin.php
// Garmin เขียนตัวเลขไว้เหนือป้ายเสมอ ตัวอย่าง 6.51 km อยู่เหนือ Distance , 45:43 อยู่เหนือ Total Time

// ได้ค่ากลับ เช่น { km: 6.51, minutes: 46, time: "45:43" } ถ้าไม่ใช่รูป Garmin ได้ null
function readGarmin(text) {
  const lines = text.split("\n");   // แยกเป็นทีละบรรทัด
  let km   = null;
  let time = null;

  for (let i = 1; i < lines.length; i++) {

    // บรรทัดข้างบน ถ้าว่างขึ้นไปอีก 1 บรรทัด
    let above = lines[i - 1];
    if (above.trim() == "" && i >= 2) {
      above = lines[i - 2];
    }

    // หาระยะทาง เลขทศนิยม เช่น 6.51
    if (lines[i].includes("Distance")) {
      const found = above.match(/\d+\.\d+/);
      if (found) {
        km = parseFloat(found[0]);
      }
    }

    // หาเวลา เช่น 45:43 หรือ 1:05:12
    if (lines[i].includes("Total Time")) {
      const found = above.match(/\d+:\d+(:\d+)?/);
      if (found) {
        time = found[0];
      }
    }
  }

  // เจอไม่ครบ = ไม่ใช่รูป Garmin
  if (km == null || time == null) {
    return null;
  }

  // แปลงเวลาเป็นนาที ตัวอย่าง 45:43 = 45 + 43/60 ปัดเป็น 46
  const parts = time.split(":");
  let minutes;
  if (parts.length == 3) {
    minutes = Number(parts[0]) * 60 + Number(parts[1]) + Number(parts[2]) / 60;
  } else {
    minutes = Number(parts[0]) + Number(parts[1]) / 60;
  }

  return { km: km, minutes: Math.round(minutes), time: time };
}
