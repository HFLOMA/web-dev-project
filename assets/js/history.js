let myChart = null;
let currentPage = 1;

window.onload = function() {
    loadHistoryData(1);
};

// ดึงข้อมูลประวัติและสถิติ
function loadHistoryData(page) {
    currentPage = page || 1;

    let periodElem = document.getElementById("filterPeriod");
    let typeElem   = document.getElementById("filterType");

    let period = periodElem ? periodElem.value : "all";
    let type   = typeElem ? typeElem.value : "all";

    let request = new XMLHttpRequest();

    request.onreadystatechange = function() {
        if (request.readyState === 4) {
            if (request.status === 200) {
                try {
                    let response = JSON.parse(request.responseText);

                    if (response.success) {
                        // อัปเดตกล่องสถิติ
                        document.getElementById("kpiSpent").innerText = response.kpi.total_spent + " เหรียญ";
                        document.getElementById("kpiItems").innerText = response.kpi.total_items + " ชิ้น";
                        document.getElementById("kpiTopup").innerText = response.kpi.total_topup + " เหรียญ";
                        document.getElementById("kpiBestSeller").innerText = response.kpi.best_seller;

                        // อัปเดตตาราง
                        renderTransactionTable(response.transactions);

                        // ปุ่มแบ่งหน้า
                        renderPagination(response.pagination);

                        let totalText = document.getElementById("totalRecordsText");
                        if (totalText) {
                            totalText.innerText = "ทั้งหมด " + response.pagination.total_rows + " รายการ";
                        }

                        // อัปเดตหัวข้อกราฟ
                        let chartHeading = document.getElementById("chartHeading");
                        if (chartHeading && response.chart && response.chart.heading) {
                            chartHeading.innerText = response.chart.heading;
                        }

                        // วาดกราฟ
                        try {
                            if (typeof Chart !== "undefined" && response.chart) {
                                updateWeeklyChart(response.chart.labels, response.chart.data, response.chart.dataset_label);
                            }
                        } catch (chartErr) {
                            console.warn(chartErr);
                        }

                    } else {
                        let tbody = document.getElementById("transactionBody");
                        if (tbody) {
                            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #ef4444; padding: 25px;">' + (response.message || "เกิดข้อผิดพลาดในการโหลดข้อมูล") + '</td></tr>';
                        }
                    }
                } catch (e) {
                    console.error("JSON Error:", e);
                }
            }
        }
    };

    let url = "api/api_history.php?period=" + encodeURIComponent(period) 
            + "&type=" + encodeURIComponent(type) 
            + "&page=" + encodeURIComponent(currentPage);

    request.open("GET", url, true);
    request.send(null);
}

// วาดกราฟแท่ง
function updateWeeklyChart(labels, dataValues, datasetLabel) {
    let canvas = document.getElementById("expenseChart");
    if (!canvas) return;

    let ctx = canvas.getContext("2d");

    let backgroundColors = labels.map((label, index) => {
        return (index === labels.length - 1) ? "#ea580c" : "#334155";
    });

    if (myChart !== null) {
        myChart.destroy();
    }

    myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: datasetLabel || 'ยอดใช้จ่าย (เหรียญ)',
                data: dataValues,
                backgroundColor: backgroundColors,
                borderRadius: 6,
                maxBarThickness: 42
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let val = Number(context.raw) || 0;
                            let label = datasetLabel || 'ยอดใช้จ่าย';
                            return label + ': ' + val.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' เหรียญ';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Sarabun', size: 12, weight: '600' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        font: { family: 'Sarabun', size: 12 },
                        callback: function(val) {
                            return Number(val).toLocaleString('th-TH');
                        }
                    }
                }
            }
        }
    });
}

// แสดงตารางรายการ
function renderTransactionTable(transactions) {
    let tbody = document.getElementById("transactionBody");
    if (!tbody) return;

    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 30px;">ไม่พบรายการในช่วงเวลานี้</td></tr>';
        return;
    }

    let html = "";
    for (let i = 0; i < transactions.length; i++) {
        let tx = transactions[i];
        let status = tx.status || "completed";
        let statusClass = "badge-" + status;
        let amountClass = "";
        let strikeStyle = "";

        if (status === "topup") {
            amountClass = "amount-plus";
        } else if (status === "completed") {
            amountClass = "amount-minus";
        } else if (status === "cancelled") {
            amountClass = "amount-muted";
            strikeStyle = "style='text-decoration: line-through; opacity: 0.6;'";
        } else if (status === "pending") {
            amountClass = "amount-pending";
        } else {
            amountClass = "amount-minus";
        }

        html += "<tr>";
        html += "  <td class='tx-date'>" + tx.date + "</td>";
        html += "  <td class='tx-title'>" + tx.title + " <span class='badge-status " + statusClass + "'>" + tx.status_label + "</span></td>";
        html += "  <td class='tx-amount " + amountClass + "' " + strikeStyle + ">" + tx.amount_formatted + "</td>";
        html += "</tr>";
    }

    tbody.innerHTML = html;
}

// สร้างปุ่มเลขหน้า
function renderPagination(pagination) {
    let container = document.getElementById("paginationControls");
    if (!container) return;

    let total = pagination.total_pages;
    let current = pagination.current_page;

    if (total <= 1) {
        container.innerHTML = "";
        return;
    }

    let html = "";

    let prevDisabled = (current === 1) ? "style='opacity:0.3; cursor:not-allowed;'" : "onclick='loadHistoryData(" + (current - 1) + ")'";
    html += "<button class='page-btn' " + prevDisabled + ">&lsaquo;</button>";

    for (let p = 1; p <= total; p++) {
        let activeClass = (p === current) ? "active" : "";
        html += "<button class='page-btn " + activeClass + "' onclick='loadHistoryData(" + p + ")'>" + p + "</button>";
    }

    let nextDisabled = (current === total) ? "style='opacity:0.3; cursor:not-allowed;'" : "onclick='loadHistoryData(" + (current + 1) + ")'";
    html += "<button class='page-btn " + nextDisabled + ">&rsaquo;</button>";

    container.innerHTML = html;
}
