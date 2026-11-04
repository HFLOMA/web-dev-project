let currentItem = { id: 0, name: '', price: 0, qty: 1 };

// ดึงยอดเหรียญปัจจุบัน
function getUserCoins() {
    if (typeof window.userCoinBalance === "number" && !isNaN(window.userCoinBalance)) {
        return window.userCoinBalance;
    }
    let coinElem = document.getElementById("userCoin");
    if (coinElem) {
        let val = parseFloat(coinElem.innerText.replace(/,/g, ''));
        if (!isNaN(val)) {
            window.userCoinBalance = val;
            return val;
        }
    }
    return 150.00;
}

// อัปเดตยอดเหรียญ
function setUserCoins(newCoin) {
    let numeric = parseFloat(newCoin) || 0;
    window.userCoinBalance = numeric;

    let coinElem = document.getElementById("userCoin");
    if (coinElem) coinElem.innerText = numeric.toLocaleString();

    let balanceElem = document.getElementById("balanceText");
    if (balanceElem) balanceElem.innerText = numeric.toLocaleString() + " เหรียญ";
}

// ปรับจำนวนชิ้นสินค้า
function changeQty(itemId, change, maxLimit) {
    let qtyInput = document.getElementById("qty_" + itemId);
    if (!qtyInput) return;

    let currentVal = parseInt(qtyInput.value) || 1;
    let max = maxLimit || 9;
    let newVal = currentVal + change;
    
    if (newVal >= 1 && newVal <= max) {
        qtyInput.value = newVal;
    }
}

// เปิด modal ยืนยันการซื้อ
function openBuyModal(itemId, itemName, price, itemImg) {
    let qtyInput = document.getElementById("qty_" + itemId);
    let qty = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
    let numericPrice = parseFloat(price) || 0;

    currentItem.id = itemId;
    currentItem.name = itemName;
    currentItem.price = numericPrice;
    currentItem.qty = qty;

    let totalPrice = numericPrice * qty;
    let currentCoin = getUserCoins();
    let remaining = currentCoin - totalPrice;

    document.getElementById("modalItemTitle").innerText = itemName + " × " + qty;
    document.getElementById("modalItemPrice").innerText = totalPrice.toLocaleString() + " เหรียญ";
    
    let imgElem = document.getElementById("modalItemImg");
    if (imgElem) {
        if (itemImg) {
            imgElem.src = itemImg;
            imgElem.style.display = "block";
        } else {
            imgElem.style.display = "none";
        }
    }

    let remainElement = document.getElementById("modalRemainCoin");
    let confirmBtn = document.getElementById("btnConfirmBuy");
    let warningText = document.getElementById("coinWarningText");

    if (remaining < 0) {
        remainElement.innerText = "ขาดอีก " + Math.abs(remaining).toLocaleString() + " เหรียญ";
        remainElement.style.color = "#dc2626";
        warningText.style.display = "block";
        confirmBtn.disabled = true;
    } else {
        remainElement.innerText = remaining.toLocaleString() + " เหรียญ";
        remainElement.style.color = "#0f172a";
        warningText.style.display = "none";
        confirmBtn.disabled = false;
    }

    document.getElementById("modalConfirm").style.display = "flex";
}

// ปิด modal ซื้อ
function closeBuyModal() {
    let modal = document.getElementById("modalConfirm");
    if (modal) modal.style.display = "none";
}

// ยิง ajax ซื้อไอเทม
function submitPurchase() {
    let confirmBtn = document.getElementById("btnConfirmBuy");
    confirmBtn.disabled = true;
    confirmBtn.innerText = "กำลังประมวลผล...";

    let request = new XMLHttpRequest();

    request.onreadystatechange = function() {
        if (request.readyState === 4) {
            confirmBtn.disabled = false;
            confirmBtn.innerText = "ยืนยันการซื้อ";

            if (request.status === 200) {
                try {
                    let response = JSON.parse(request.responseText);

                    if (response.success) {
                        alert(response.message + "\nรายการ: " + response.item_name + " (" + response.qty + " ชิ้น)\nยอดคงเหลือ: " + Number(response.new_coin).toLocaleString() + " เหรียญ");

                        setUserCoins(response.new_coin);

                        let shieldElem = document.getElementById("userShield");
                        if (shieldElem) shieldElem.innerText = response.new_shield;

                        let shieldText = document.getElementById("balanceShieldText");
                        if (shieldText && response.new_shield !== undefined) shieldText.innerText = response.new_shield;

                        let repairText = document.getElementById("balanceRepairText");
                        if (repairText && response.new_repair !== undefined) repairText.innerText = response.new_repair;

                        let insuranceText = document.getElementById("balanceInsuranceText");
                        if (insuranceText && response.new_insurance !== undefined) insuranceText.innerText = response.new_insurance;

                        closeBuyModal(); location.reload();
                    } else {
                        alert(response.message);
                    }
                } catch (e) {
                    alert("เกิดข้อผิดพลาดในการรับข้อมูล");
                }
            } else {
                alert("เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ (รหัส " + request.status + ")");
            }
        }
    };

    request.open("POST", "api/api_buy.php", true);
    request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

    let params = "item_id=" + encodeURIComponent(currentItem.id) + "&qty=" + encodeURIComponent(currentItem.qty);
    request.send(params);
}

// เปิด modal เติมเงิน
function openTopupModal() {
    let modal = document.getElementById("modalTopup");
    if (modal) modal.style.display = "flex";
    let input = document.getElementById("topupAmount");
    if (input) input.value = 100;
}

// ปิด modal เติมเงิน
function closeTopupModal() {
    let modal = document.getElementById("modalTopup");
    if (modal) modal.style.display = "none";
}

// ปุ่มลัดเลือกจำนวนเงิน
function setTopupPreset(amount) {
    let input = document.getElementById("topupAmount");
    if (input) input.value = amount;
}

// ยิง ajax เติมเงิน
function submitTopup() {
    let input = document.getElementById("topupAmount");
    let amount = input ? parseFloat(input.value) : 0;

    if (!amount || amount <= 0) {
        alert("กรุณาระบุจำนวนเหรียญที่ถูกต้อง");
        return;
    }

    let request = new XMLHttpRequest();
    request.onreadystatechange = function() {
        if (request.readyState === 4 && request.status === 200) {
            try {
                let response = JSON.parse(request.responseText);
                if (response.success) {
                    alert(response.message);
                    setUserCoins(response.new_coin);
                    closeTopupModal();
                } else {
                    alert(response.message);
                }
            } catch (e) {
                alert("เกิดข้อผิดพลาดในการเติมเงิน");
            }
        }
    };

    request.open("POST", "api/api_topup.php", true);
    request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    request.send("amount=" + encodeURIComponent(amount));
}
