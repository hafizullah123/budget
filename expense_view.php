<?php
/* ================= DATABASE CONNECTION ================= */
$conn = new mysqli("localhost","root","","budget1");
if($conn->connect_error){
    die("Connection failed: ".$conn->connect_error);
}

/* ================= FETCH ALL VOUCHERS ================= */
$voucherResult = $conn->query("SELECT * FROM expense_vouchers ORDER BY id DESC");
$vouchers = [];
while($row = $voucherResult->fetch_assoc()){
    $vouchers[$row['id']] = $row;
}

$voucher_ids = array_keys($vouchers);
$items = $recipients = $banks = [];

if($voucher_ids){
    $ids = implode(",", $voucher_ids);

    $itemResult = $conn->query("SELECT * FROM expense_voucher_items WHERE voucher_id IN ($ids) ORDER BY id ASC");
    while($row = $itemResult->fetch_assoc()){
        $items[$row['voucher_id']][] = $row;
    }

    $recipientResult = $conn->query("SELECT * FROM expense_recipients WHERE voucher_id IN ($ids)");
    while($row = $recipientResult->fetch_assoc()){
        $recipients[$row['voucher_id']] = $row;
    }

    $bankResult = $conn->query("SELECT * FROM expense_recipient_banks WHERE voucher_id IN ($ids)");
    while($row = $bankResult->fetch_assoc()){
        $banks[$row['voucher_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لیست سندهای مصرف</title>
<style>
body{font-family:'Segoe UI', Tahoma, Arial; background:#f0f2f5; margin:0; padding:20px;}
h2{text-align:center; margin-bottom:20px; color:#333;}
#searchBox {width:100%; padding:10px; margin-bottom:20px; font-size:15px; border:1px solid #ccc; border-radius:5px;}

/* Card Layout */
.voucher-card{background:#fff; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); margin-bottom:20px; overflow:hidden; transition:0.3s;}
.voucher-header{background:#007bff; color:#fff; padding:12px 15px; font-size:14px; display:flex; justify-content:space-between; align-items:center; cursor:pointer;}
.voucher-header div{flex:1; text-align:center;}
.voucher-content{padding:15px; display:none; border-top:1px solid #ddd;}
.section-title{background:#20c997; color:#fff; padding:5px 10px; font-size:13px; margin-bottom:8px; border-radius:5px; display:inline-block;}

/* Tables inside content */
.voucher-content table{width:100%; border-collapse:collapse; margin-bottom:10px;}
.voucher-content th, .voucher-content td{border:1px solid #ddd; padding:6px; font-size:13px; text-align:center;}
.voucher-content th{background:#ffc107; color:#fff; font-weight:600;}
.voucher-content tr:nth-child(even){background:#f9f9f9;}

/* Hover effect for card */
.voucher-card:hover{box-shadow:0 6px 18px rgba(0,0,0,0.15);}
</style>
</head>
<body>

<h2>لیست سندهای مصرف</h2>
<input type="text" id="searchBox" placeholder="جستجو براساس شماره سند، نوعیت مصرف، سال، نام و کد...">

<div id="voucherContainer">
<?php foreach($vouchers as $vid => $voucher): ?>
<div class="voucher-card">
    <div class="voucher-header" onclick="toggleCard(this)">
        <div>شماره سند: <?= htmlspecialchars($voucher['voucher_number']) ?></div>
        <div>نوعیت مصرف: <?= htmlspecialchars($voucher['expense_type']) ?></div>
        <div>کال: <?= htmlspecialchars($voucher['year']) ?></div>
        <div>نېټه: <?= htmlspecialchars($voucher['voucher_date']) ?></div>
    </div>
    <div class="voucher-content">
        <!-- Voucher Summary -->
        <div class="section-title">جزئیات سند</div>
        <table>
            <tr>
                <th>مجموعه ډبیټ</th><th>مجموعه کریډیټ</th><th>د تادیی وړ مبلغ</th><th>طریقه تادیه</th>
            </tr>
            <tr>
                <td><?= htmlspecialchars($voucher['total_debit']) ?></td>
                <td><?= htmlspecialchars($voucher['total_credit']) ?></td>
                <td><?= htmlspecialchars($voucher['payable_amount']) ?></td>
                <td><?= htmlspecialchars($voucher['payment_method']) ?></td>
            </tr>
        </table>

        <!-- Voucher Items -->
        <?php if(isset($items[$vid])): ?>
        <div class="section-title">تفصیلات</div>
        <table>
            <tr>
                <th>تفصیلات</th><th>عمومي کوډ</th><th>فرعی کوډ</th><th>ډبیټ</th><th>کریډیټ</th>
            </tr>
            <?php foreach($items[$vid] as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['details']) ?></td>
                <td><?= htmlspecialchars($item['general_code']) ?></td>
                <td><?= htmlspecialchars($item['sub_code']) ?></td>
                <td><?= htmlspecialchars($item['debit']) ?></td>
                <td><?= htmlspecialchars($item['credit']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <!-- Recipient Info -->
        <?php if(isset($recipients[$vid])): ?>
        <div class="section-title">د ترلاسه کوونکی</div>
        <table>
            <tr><th>نام</th><th>شماره</th><th>سیستم شماره</th></tr>
            <tr>
                <td><?= htmlspecialchars($recipients[$vid]['recipient_name']) ?></td>
                <td><?= htmlspecialchars($recipients[$vid]['payer_recipient_number']) ?></td>
                <td><?= htmlspecialchars($recipients[$vid]['system_recipient_number']) ?></td>
            </tr>
        </table>
        <?php endif; ?>

        <!-- Bank Info -->
        <?php if(isset($banks[$vid])): ?>
        <div class="section-title">بانک</div>
        <table>
            <tr><th>نام بانک</th><th>شماره حساب</th><th>انوایس</th><th>آدرس</th></tr>
            <tr>
                <td><?= htmlspecialchars($banks[$vid]['bank_name']) ?></td>
                <td><?= htmlspecialchars($banks[$vid]['bank_account']) ?></td>
                <td><?= htmlspecialchars($banks[$vid]['invoice_id']) ?></td>
                <td><?= htmlspecialchars($banks[$vid]['bank_address']) ?></td>
            </tr>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<script>
// Toggle card content
function toggleCard(el){
    let content = el.nextElementSibling;
    content.style.display = content.style.display === 'block' ? 'none' : 'block';
}

// Live search
document.getElementById('searchBox').addEventListener('input', function(){
    let query = this.value.toLowerCase();
    let cards = document.querySelectorAll('#voucherContainer .voucher-card');
    cards.forEach(card=>{
        let text = card.textContent.toLowerCase();
        card.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>

</body>
</html>
