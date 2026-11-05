<?php

// กัน Cross-Site Scripting
function e($text)
{
    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
}

//ดึงข้อมุูลของคนใช้ 
function get_user($pdo, $user_id)
{
    $stmt = $pdo->prepare("SELECT * FROM paceup__users WHERE id = ?");
    $stmt->bindParam(1, $user_id);
    $stmt->execute();
    return $stmt->fetch();
}

function check_streak($pdo, $user_id)
{
    $user      = get_user($pdo, $user_id);
    $streak    = $user["current_streak"];
    $shield    = $user["shield_balance"];
    $yesterday = date("Y-m-d", strtotime("yesterday"));

    // เช็ควันสุดท้าย ที่เข้าระบบ หรือ วันที่ มีitem ช่วยกันเอาไว้
    $last = max($user["last_checkin_date"], $user["last_freeze_date"]);

    // ไม่มี Streak หรือยังไม่ขาด ไม่ทําอะไร
    if ($streak == 0 || $last == null || $last >= $yesterday) {
        return null;
    }

    //คิดว่าหายไปกี่วัน
    $missed = round((strtotime($yesterday) - strtotime($last)) / (60*60*24 ));

    if ($shield >= $missed) {
        // ลบitemตามวันที่ขาดไป
        $stmt = $pdo->prepare("UPDATE paceup__users SET shield_balance = shield_balance - ?, last_freeze_date = ? WHERE id = ?");
        $stmt->bindParam(1, $missed);
        $stmt->bindParam(2, $yesterday);
        $stmt->bindParam(3, $user_id);
        $stmt->execute();

        // บันทึกในประวัติการใช้ 
        for ($i = 0; $i < $missed; $i++) {
            $stmt = $pdo->prepare("INSERT INTO paceup__item_usages (user_id, item_id, usage_type) VALUES (?, 1, 'auto_protect_streak')");
            $stmt->bindParam(1, $user_id);
            $stmt->execute();
        }
        return ["approved", "ไม่ได้วิ่ง $missed วัน ใช้ Shield อัตโนมัติไป $missed อัน Streak ยังอยู่"];
    }

    //item ไม่พอ ทํา streak เป็น 0 แต่จำค่าเดิมกับเวลาที่หลุดไว้ ให้ Repair / Insurance กู้คืน
    $stmt = $pdo->prepare("UPDATE paceup__users SET current_streak = 0, lost_streak = ?, streak_lost_at = NOW() WHERE id = ?");
    $stmt->bindParam(1, $streak);
    $stmt->bindParam(2, $user_id);
    $stmt->execute();
    return ["rejected", "Streak $streak วัน หลุดแล้ว เพราะไม่ได้วิ่ง $missed วัน และ Shield ไม่พอ"];
}

// คํานวนระยะทางวิ่ง
function get_goal($user)
{
    
    if ($user["experience"] == "serious") {
        $level = "จริงจัง";
        $base  = 30;
    } elseif ($user["experience"] == "regular") {
        $level = "วิ่งประจำ";
        $base  = 18;
    } else {
        $level = "มือใหม่";
        $base  = 10;
    }

    
    // ไม่มีส่วนสูงหรือน้ำหนัก คิด BMI ไม่ได้ ให้ bmi = 0 (ตัวคูณ 1)
    if (empty($user["height"]) || empty($user["weight"])) {
        $bmi = 0;
    } else {
        $bmi = $user["weight"] / (($user["height"] / 100) ** 2);
    }
    if ($bmi >= 30) {
        $bmi_rate = 0.6;
    } elseif ($bmi >= 25) {
        $bmi_rate = 0.8;
    } else {
        $bmi_rate = 1;
    }

    return [
        "level"    => $level,
        "base"     => $base,
        "bmi"      => $bmi,
        "bmi_rate" => $bmi_rate,
        "goal"     => round($base * $bmi_rate),
    ];
}
?>
