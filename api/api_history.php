<?php
// API ดึงข้อมูลประวัติการซื้อและสถิติ
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connect.php';

// เช็ค session
if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อน"]);
    exit;
}

$user_id = intval($_SESSION["user_id"]);

// รับค่าตัวกรองและหน้า
$period = trim($_GET["period"] ?? "all");
$type   = trim($_GET["type"] ?? "all");
$page   = max(1, intval($_GET["page"] ?? 1));
$limit  = 4;

// ฟังก์ชันแปลงวันที่เป็นภาษาไทยสั้นๆ เช่น 15 พ.ย.
if (!function_exists('formatThaiShortDate')) {
    function formatThaiShortDate($dateStr) {
        if (!$dateStr) return "-";
        $months = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];
        $t = strtotime($dateStr);
        if (!$t) return "-";
        $d = date('j', $t);
        $m = $months[intval(date('n', $t))];
        return "$d $m";
    }
}

try {
    // เงื่อนไขเวลาสำหรับคำนวณ KPI
    $where = ["o.user_id = ?"];
    $params = [$user_id];

    if ($period === "7") {
        $where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    } else if ($period === "30") {
        $where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
    }
    $where_sql = implode(" AND ", $where);

    // ยอดใช้จ่ายรวม
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM paceup__orders o WHERE $where_sql AND o.status = 'completed'");
    $stmt->execute($params);
    $total_spent = floatval($stmt->fetchColumn());

    // จำนวนชิ้นที่ซื้อ
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(oi.qty), 0) 
        FROM paceup__orders o 
        JOIN paceup__order_items oi ON o.id = oi.order_id 
        WHERE $where_sql AND o.status = 'completed'");
    $stmt->execute($params);
    $total_items = intval($stmt->fetchColumn());

    // ยอดเติมเงินรวม
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM paceup__orders o WHERE $where_sql AND o.status = 'topup'");
    $stmt->execute($params);
    $total_topup = floatval($stmt->fetchColumn());

    // ไอเทมที่ขายดีสุด
    $stmt = $pdo->prepare("SELECT i.name, SUM(oi.qty) AS total_qty
        FROM paceup__orders o
        JOIN paceup__order_items oi ON o.id = oi.order_id
        JOIN paceup__items i ON oi.item_id = i.id
        WHERE $where_sql AND o.status = 'completed'
        GROUP BY i.id, i.name
        ORDER BY total_qty DESC
        LIMIT 1");
    $stmt->execute($params);
    $best = $stmt->fetch();
    $best_seller = $best ? $best["name"] : "ยังไม่มีข้อมูล";

    // ข้อมูลสำหรับวาดกราฟแท่ง (Chart.js)
    $status_cond = ($type === 'topup') ? "status = 'topup'" : "status = 'completed'";
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

    if ($period === "7") {
        // ย้อนหลัง 7 วันรายวัน
        $chart_heading .= "รายวัน (7 วันล่าสุด)";

        for ($i = 6; $i >= 0; $i--) {
            $t = strtotime("today -$i day");
            $d_date  = date('Y-m-d', $t);
            $d_name  = $thai_days[intval(date('w', $t))];
            $d_num   = date('j', $t);
            $d_label = "$d_name $d_num";

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM paceup__orders 
                WHERE user_id = ? AND $status_cond AND DATE(created_at) = ?");
            $stmt->execute([$user_id, $d_date]);
            $spent = floatval($stmt->fetchColumn() ?? 0);

            $chart_labels[] = $d_label;
            $chart_data[]   = round($spent, 2);
        }
    } else if ($period === "30") {
        // ย้อนหลัง 4 สัปดาห์
        $chart_heading .= "รอบ 4 สัปดาห์ (30 วันล่าสุด)";

        $intervals = [
            [29, 22],
            [21, 15],
            [14, 8],
            [7, 0]
        ];

        foreach ($intervals as $days) {
            $start_t = strtotime("today -{$days[0]} day");
            $end_t   = strtotime("today -{$days[1]} day");

            $start_str = date('Y-m-d 00:00:00', $start_t);
            $end_str   = date('Y-m-d 23:59:59', $end_t);

            $s_d = date('j', $start_t);
            $s_m = $thai_months[intval(date('n', $start_t))];
            $e_d = date('j', $end_t);
            $e_m = $thai_months[intval(date('n', $end_t))];

            $label = ($s_m === $e_m) ? "$s_d - $e_d $e_m" : "$s_d $s_m - $e_d $e_m";

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM paceup__orders 
                WHERE user_id = ? AND $status_cond AND created_at >= ? AND created_at <= ?");
            $stmt->execute([$user_id, $start_str, $end_str]);
            $spent = floatval($stmt->fetchColumn() ?? 0);

            $chart_labels[] = $label;
            $chart_data[]   = round($spent, 2);
        }
    } else {
        // ทั้งหมด: สรุปเป็นรายเดือน
        $chart_heading .= "รายเดือน (ทั้งหมด)";

        $stmt = $pdo->prepare("SELECT MIN(created_at) FROM paceup__orders WHERE user_id = ? AND $status_cond");
        $stmt->execute([$user_id]);
        $min_date = $stmt->fetchColumn();

        $cur_year  = intval(date('Y'));
        $cur_month = intval(date('n'));

        if ($min_date) {
            $start_year  = intval(date('Y', strtotime($min_date)));
            $start_month = intval(date('n', strtotime($min_date)));
        } else {
            $start_year  = intval(date('Y', strtotime("-2 month")));
            $start_month = intval(date('n', strtotime("-2 month")));
        }

        $slots = [];
        $y = $start_year;
        $m = $start_month;

        while ($y < $cur_year || ($y === $cur_year && $m <= $cur_month)) {
            $slots[] = ['year' => $y, 'month' => $m];
            $m++;
            if ($m > 12) {
                $m = 1;
                $y++;
            }
        }

        while (count($slots) < 3) {
            $first = $slots[0];
            $prev_m = $first['month'] - 1;
            $prev_y = $first['year'];
            if ($prev_m < 1) {
                $prev_m = 12;
                $prev_y--;
            }
            array_unshift($slots, ['year' => $prev_y, 'month' => $prev_m]);
        }

        foreach ($slots as $s) {
            $y_val = $s['year'];
            $m_val = $s['month'];
            $be_year = ($y_val + 543) % 100;
            $label = $thai_months[$m_val] . " " . $be_year;

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM paceup__orders 
                WHERE user_id = ? AND $status_cond AND YEAR(created_at) = ? AND MONTH(created_at) = ?");
            $stmt->execute([$user_id, $y_val, $m_val]);
            $spent = floatval($stmt->fetchColumn() ?? 0);

            $chart_labels[] = $label;
            $chart_data[]   = round($spent, 2);
        }
    }

    // ดึงตารางประวัติคำสั่งซื้อ
    $table_where = ["o.user_id = ?"];
    $table_params = [$user_id];

    if ($period === "7") {
        $table_where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    } else if ($period === "30") {
        $table_where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
    }

    if ($type === "buy") {
        $table_where[] = "o.status IN ('completed', 'pending', 'cancelled')";
    } else if ($type === "topup") {
        $table_where[] = "o.status = 'topup'";
    }

    $table_where_sql = implode(" AND ", $table_where);

    // นับจำนวนหน้า
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM paceup__orders o WHERE $table_where_sql");
    $stmt->execute($table_params);
    $total_rows  = intval($stmt->fetchColumn());
    $total_pages = max(1, ceil($total_rows / $limit));
    $offset      = ($page - 1) * $limit;

    // รายการของหน้านี้
    $stmt = $pdo->prepare("SELECT 
            o.id AS order_id,
            o.created_at,
            o.status,
            o.total_price,
            (SELECT GROUP_CONCAT(CONCAT(i.name, ' × ', oi.qty) SEPARATOR ', ')
             FROM paceup__order_items oi
             JOIN paceup__items i ON oi.item_id = i.id
             WHERE oi.order_id = o.id) AS item_names
        FROM paceup__orders o
        WHERE $table_where_sql
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT $limit OFFSET $offset");
    $stmt->execute($table_params);
    $rows = $stmt->fetchAll();

    $transactions = [];
    foreach ($rows as $row) {
        $is_topup = ($row["status"] === "topup");
        $item_name = !empty($row["item_names"]) ? $row["item_names"] : "ไอเทมดิจิทัล";

        if ($is_topup) {
            $title = "เติมเงิน (จำลอง)";
            $amount_formatted = "+" . number_format($row["total_price"], 0);
            $status_label = "สำเร็จ";
        } else {
            $title = "ซื้อ " . $item_name;
            $amount_formatted = "-" . number_format($row["total_price"], 0);
            $status_label = ($row["status"] === 'completed') ? "สำเร็จ" : $row["status"];
        }

        $transactions[] = [
            "id"               => $row["order_id"],
            "date"             => formatThaiShortDate($row["created_at"]),
            "title"            => $title,
            "type"             => $is_topup ? "topup" : "buy",
            "status"           => $row["status"],
            "status_label"     => $status_label,
            "amount"           => floatval($row["total_price"]),
            "amount_formatted" => $amount_formatted
        ];
    }

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

} catch (Throwable $e) {
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
}
?>