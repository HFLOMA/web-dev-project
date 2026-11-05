<?php
// API ดึงข้อมูลประวัติธุรกรรมและสถิติ (สำหรับหน้า history.php)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connect.php';

// เช็คว่าล็อกอินหรือยัง
if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อน"]);
    exit;
}

$user_id = intval($_SESSION["user_id"]);

// รับค่าตัวกรองจากหน้าเว็บ
$period = isset($_GET["period"]) ? trim($_GET["period"]) : "all";
$type   = isset($_GET["type"]) ? trim($_GET["type"]) : "all";
$page   = isset($_GET["page"]) ? max(1, intval($_GET["page"])) : 1;
$limit  = 4; // แสดงหน้าละ 4 รายการ

// ฟังก์ชันแปลงวันที่เป็นภาษาไทยสั้นๆ เช่น "15 พ.ย."
function formatThaiShortDate($date_str) {
    if (!$date_str) return "-";
    $months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $time = strtotime($date_str);
    if (!$time) return "-";
    $day   = date('j', $time);
    $month = $months[intval(date('n', $time))];
    return "$day $month";
}

try {
    // ---------------------------------------------------------
    // 1. คำนวณช่วงวันที่ตามตัวกรอง (7 วัน, 30 วัน, หรือ ทั้งหมด)
    // ---------------------------------------------------------
    $start_date = "";
    if ($period == "7") {
        $start_date = date("Y-m-d 00:00:00", strtotime("-6 days"));
    } else if ($period == "30") {
        $start_date = date("Y-m-d 00:00:00", strtotime("-29 days"));
    }

    // ---------------------------------------------------------
    // 2. คำนวณกล่องสถิติ KPI 4 กล่อง
    // ---------------------------------------------------------

    // ยอดใช้จ่ายรวม (เฉพาะออเดอร์ที่ซื้อไอเทมสำเร็จ)
    if ($start_date != "") {
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM paceup__orders 
                               WHERE user_id = ? AND status = 'completed' AND created_at >= ?");
        $stmt->execute([$user_id, $start_date]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM paceup__orders 
                               WHERE user_id = ? AND status = 'completed'");
        $stmt->execute([$user_id]);
    }
    $total_spent = floatval($stmt->fetchColumn() ?: 0);

    // ยอดเติมเงินรวม
    if ($start_date != "") {
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM paceup__orders 
                               WHERE user_id = ? AND status = 'topup' AND created_at >= ?");
        $stmt->execute([$user_id, $start_date]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM paceup__orders 
                               WHERE user_id = ? AND status = 'topup'");
        $stmt->execute([$user_id]);
    }
    $total_topup = floatval($stmt->fetchColumn() ?: 0);

    // จำนวนชิ้นที่ซื้อทั้งหมด (นับจากตาราง order_items)
    if ($start_date != "") {
        $sql = "SELECT SUM(oi.qty) 
                FROM paceup__order_items oi 
                JOIN paceup__orders o ON oi.order_id = o.id 
                WHERE o.user_id = ? AND o.status = 'completed' AND o.created_at >= ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $start_date]);
    } else {
        $sql = "SELECT SUM(oi.qty) 
                FROM paceup__order_items oi 
                JOIN paceup__orders o ON oi.order_id = o.id 
                WHERE o.user_id = ? AND o.status = 'completed'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    }
    $total_items = intval($stmt->fetchColumn() ?: 0);

    // ไอเทมที่ซื้อบ่อยสุด (ขายดีสุดของคนนี้)
    if ($start_date != "") {
        $sql = "SELECT i.name, SUM(oi.qty) AS sum_qty 
                FROM paceup__order_items oi
                JOIN paceup__orders o ON oi.order_id = o.id
                JOIN paceup__items i ON oi.item_id = i.id
                WHERE o.user_id = ? AND o.status = 'completed' AND o.created_at >= ?
                GROUP BY i.name
                ORDER BY sum_qty DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $start_date]);
    } else {
        $sql = "SELECT i.name, SUM(oi.qty) AS sum_qty 
                FROM paceup__order_items oi
                JOIN paceup__orders o ON oi.order_id = o.id
                JOIN paceup__items i ON oi.item_id = i.id
                WHERE o.user_id = ? AND o.status = 'completed'
                GROUP BY i.name
                ORDER BY sum_qty DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    }
    $best_item = $stmt->fetch();
    $best_seller = $best_item ? $best_item["name"] : "ยังไม่มีข้อมูล";


    // ---------------------------------------------------------
    // 3. เตรียมข้อมูลสำหรับวาดกราฟแท่ง (Chart.js)
    // ---------------------------------------------------------
    $target_status = ($type === 'topup') ? 'topup' : 'completed';
    $dataset_label = ($type === 'topup') ? "ยอดเติมเงิน (เหรียญ)" : "ยอดใช้จ่าย (เหรียญ)";
    $chart_heading = ($type === 'topup') ? "ยอดเติมเงิน" : "ยอดใช้จ่าย";

    $chart_labels = [];
    $chart_data   = [];

    $thai_days = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
    $thai_months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];

    if ($period == "7") {
        // กราฟ 7 วันล่าสุด (รายวัน)
        $chart_heading .= "รายวัน (7 วันล่าสุด)";

        for ($i = 6; $i >= 0; $i--) {
            $day_time = strtotime("-$i days");
            $day_date = date("Y-m-d", $day_time);
            $day_name = $thai_days[intval(date("w", $day_time))];
            $day_num  = date("j", $day_time);

            $stmt = $pdo->prepare("SELECT SUM(total_price) FROM paceup__orders 
                                   WHERE user_id = ? AND status = ? AND DATE(created_at) = ?");
            $stmt->execute([$user_id, $target_status, $day_date]);
            $sum_day = floatval($stmt->fetchColumn() ?: 0);

            $chart_labels[] = "$day_name $day_num";
            $chart_data[]   = round($sum_day, 2);
        }

    } else if ($period == "30") {
        // กราฟ 30 วันล่าสุด (แบ่งเป็น 4 สัปดาห์)
        $chart_heading .= "รอบ 4 สัปดาห์ (30 วันล่าสุด)";

        $week_ranges = [
            [29, 22],
            [21, 15],
            [14, 8],
            [7, 0]
        ];

        foreach ($week_ranges as $range) {
            $from_time = strtotime("-{$range[0]} days");
            $to_time   = strtotime("-{$range[1]} days");

            $from_str = date("Y-m-d 00:00:00", $from_time);
            $to_str   = date("Y-m-d 23:59:59", $to_time);

            $s_day   = date("j", $from_time);
            $s_month = $thai_months[intval(date("n", $from_time))];
            $e_day   = date("j", $to_time);
            $e_month = $thai_months[intval(date("n", $to_time))];

            if ($s_month == $e_month) {
                $label = "$s_day - $e_day $e_month";
            } else {
                $label = "$s_day $s_month - $e_day $e_month";
            }

            $stmt = $pdo->prepare("SELECT SUM(total_price) FROM paceup__orders 
                                   WHERE user_id = ? AND status = ? AND created_at >= ? AND created_at <= ?");
            $stmt->execute([$user_id, $target_status, $from_str, $to_str]);
            $sum_week = floatval($stmt->fetchColumn() ?: 0);

            $chart_labels[] = $label;
            $chart_data[]   = round($sum_week, 2);
        }

    } else {
        // กราฟทั้งหมด: แสดง 6 เดือนล่าสุด เรียบง่ายเข้าใจง่าย
        $chart_heading .= "รายเดือน (ทั้งหมด)";

        for ($i = 5; $i >= 0; $i--) {
            $month_time  = strtotime("-$i month");
            $year_num    = intval(date("Y", $month_time));
            $month_num   = intval(date("n", $month_time));
            $thai_year   = ($year_num + 543) % 100;
            $month_label = $thai_months[$month_num] . " " . $thai_year;

            $stmt = $pdo->prepare("SELECT SUM(total_price) FROM paceup__orders 
                                   WHERE user_id = ? AND status = ? AND YEAR(created_at) = ? AND MONTH(created_at) = ?");
            $stmt->execute([$user_id, $target_status, $year_num, $month_num]);
            $sum_month = floatval($stmt->fetchColumn() ?: 0);

            $chart_labels[] = $month_label;
            $chart_data[]   = round($sum_month, 2);
        }
    }


    // ---------------------------------------------------------
    // 4. ดึงข้อมูลตารางคำสั่งซื้อ (Transactions Table & Pagination)
    // ---------------------------------------------------------
    // สร้างเงื่อนไข WHERE แบบเรียบง่าย
    $conditions = ["user_id = ?"];
    $bind_values = [$user_id];

    // กรองตามเวลา
    if ($start_date != "") {
        $conditions[] = "created_at >= ?";
        $bind_values[] = $start_date;
    }

    // กรองตามประเภทธุรกรรม
    if ($type == "buy") {
        $conditions[] = "status IN ('completed', 'pending', 'cancelled')";
    } else if ($type == "topup") {
        $conditions[] = "status = 'topup'";
    }

    $where_sql = implode(" AND ", $conditions);

    // นับจำนวนรายการทั้งหมดเพื่อทำปุ่มเลขหน้า
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM paceup__orders WHERE $where_sql");
    $stmt->execute($bind_values);
    $total_rows = intval($stmt->fetchColumn());

    $total_pages = max(1, ceil($total_rows / $limit));
    $offset      = ($page - 1) * $limit;

    // ดึงรายการคำสั่งซื้อของหน้านั้น
    // ใช้ LIMIT และ OFFSET ตรงๆ ไม่ต้องใช้ Subquery ซับซ้อน
    $sql_orders = "SELECT id, total_price, status, created_at 
                   FROM paceup__orders 
                   WHERE $where_sql 
                   ORDER BY created_at DESC, id DESC 
                   LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql_orders);
    $stmt->execute($bind_values);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // แปลงข้อมูลเพื่อส่งกลับไปให้หน้าเว็บแสดงผล
    $transactions = [];
    foreach ($orders as $ord) {
        $order_id = $ord["id"];
        $is_topup = ($ord["status"] === "topup");

        if ($is_topup) {
            $title = "เติมเงิน (จำลอง)";
            $amount_formatted = "+" . number_format($ord["total_price"], 0);
            $status_label = "สำเร็จ";
        } else {
            // ดึงชื่อไอเทมของออเดอร์นี้จากตาราง order_items แบบง่ายๆ
            $stmt_items = $pdo->prepare("SELECT i.name, oi.qty 
                                         FROM paceup__order_items oi 
                                         JOIN paceup__items i ON oi.item_id = i.id 
                                         WHERE oi.order_id = ?");
            $stmt_items->execute([$order_id]);
            $items_list = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            $item_text_arr = [];
            foreach ($items_list as $it) {
                $item_text_arr[] = $it["name"] . " × " . $it["qty"];
            }

            $item_name = !empty($item_text_arr) ? implode(", ", $item_text_arr) : "ไอเทมดิจิทัล";
            $title = "ซื้อ " . $item_name;
            $amount_formatted = "-" . number_format($ord["total_price"], 0);
            $status_label = ($ord["status"] === 'completed') ? "สำเร็จ" : $ord["status"];
        }

        $transactions[] = [
            "id"               => $order_id,
            "date"             => formatThaiShortDate($ord["created_at"]),
            "title"            => $title,
            "type"             => $is_topup ? "topup" : "buy",
            "status"           => $ord["status"],
            "status_label"     => $status_label,
            "amount"           => floatval($ord["total_price"]),
            "amount_formatted" => $amount_formatted
        ];
    }

    // ส่งผลลัพธ์กลับเป็น JSON ให้หน้าเว็บ
    echo json_encode([
        "success" => true,
        "kpi" => [
            "total_spent" => number_format($total_spent, 0),
            "total_items" => number_format($total_items, 0),
            "total_topup" => number_format($total_topup, 0),
            "best_seller" => $best_seller
        ],
        "chart" => [
            "heading"       => $chart_heading,
            "dataset_label" => $dataset_label,
            "labels"        => $chart_labels,
            "data"          => $chart_data
        ],
        "pagination" => [
            "current_page" => $page,
            "total_pages"  => $total_pages,
            "total_rows"   => $total_rows
        ],
        "transactions" => $transactions
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false, 
        "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
    ]);
}
?>