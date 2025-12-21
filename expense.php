<?php
/* DATABASE CONNECTION */
$conn = new mysqli("localhost","root","","budget1");
if($conn->connect_error){
    die("Connection failed: ".$conn->connect_error);
}

/* FETCH DISTINCT BAB VALUES */
$babResult = $conn->query("
    SELECT DISTINCT bab 
    FROM budget_details 
    ORDER BY bab ASC
");
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>سند مصرف</title>
<style>
body{font-family:Tahoma, Arial; background:#fff;}
.page{width:300mm; margin:auto; padding:10mm; border:2px solid #000;}
table{width:100%; border-collapse:collapse; font-size:13px;}
td, th{border:1px solid #000; padding:6px; vertical-align:middle;}
.no-border td{border:none;}
.center{text-align:center;}
.right{text-align:right;}
.left{text-align:left;}
.gray{background:#e6e6e6; font-weight:bold;}
input[type=text], input[type=number], input[type=date], select{
    width:100%; padding:4px; box-sizing:border-box;
}
select.scrollable {
    max-height:150px;
    overflow-y:auto;
    display:block;
}
button{padding:8px 15px; cursor:pointer;}
@media print{button{display:none;}}
</style>
</head>
<body>
<div class="page">

<form action="expense_submit.php" method="POST">

<!-- HEADER -->
<table class="no-border">
<tr>
<td class="center">
    <strong>د افغانستان اسلامی امارت</strong><br>
    امارتی شرکتونو لوی ریاست<br>
</td>
</tr>
</table>

<!-- EXPENSE TYPE DROPDOWN -->
<table>
<tr class="center gray">
    <td>نوعیت مصرف</td>
</tr>
<tr class="center">
    <td>
        <select name="expense_type" class="scrollable">
            <?php
            $babResult->data_seek(0); // reset pointer
            while($row = $babResult->fetch_assoc()):
            ?>
                <option value="<?php echo htmlspecialchars($row['bab']); ?>">
                    <?php echo htmlspecialchars($row['bab']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </td>
</tr>
</table>

<br>

<!-- RIGHT & LEFT HEADERS -->
<table>
<tr class="gray center">
    <td colspan="3">معلومات سند</td>
    <td colspan="4">معلومات سیسټم</td>
</tr>
<tr>
    <td class="right">سند شمېره</td><td colspan="2"><input type="text" name="voucher_number"></td>
    <td class="right">مالی سیسټم شمېره</td><td colspan="3"><input type="text" name="system_number"></td>
</tr>
<tr>
    <td class="right">نېټه</td><td colspan="2"><input type="date" name="voucher_date"></td>
    <td class="right">نېټه</td><td colspan="3"><input type="date" name="system_date"></td>
</tr>
<tr>
    <td class="right">کال</td><td colspan="2"><input type="text" name="year"></td>
    <td class="right">د سګټاس شمېره</td><td colspan="3"><input type="text" name="sgtas_number"></td>
</tr>
<tr>
    <td colspan="3"></td>
    <td class="right">د سکن شمېره</td><td colspan="3"><input type="text" name="scan_number"></td>
</tr>
</table>

<br>

<!-- FIRST HEADER ROW -->
<table>
<tr class="gray center">
    <td>اسعار</td>
    <td>واحد پول</td>
    <td>اداری کوډ</td>
</tr>
<tr class="center">
    <td><input type="text" name="asaar"></td>
    <td>افغانی</td>
    <td>۱۹۴۰۰۰</td>
</tr>
</table>

<br>

<!-- SECOND HEADER ROW -->
<table>
    <colgroup>
        <col style="width:60%">
        <col style="width:10%">
        <col style="width:10%">
        <col style="width:10%">
        <col style="width:10%">
    </colgroup>

<tr class="gray center">
    <td>تفصیلات</td>
    <td>عمومي کوډ</td>
    <td>فرعی کوډ</td>
    <td>ډبیټ</td>
    <td>کریډیټ</td>
</tr>

<tr class="center">
    <td><input type="text" name="details[]"></td>
    <td><input type="text" name="general_code[]"></td>
    <td><input type="text" name="sub_code[]"></td>
    <td><input type="number" name="debit[]" step="0.01"></td>
    <td><input type="number" name="credit[]" step="0.01"></td>
</tr>
</table>

<br>

<!-- TOTALS ROW -->
<table>
<tr class="gray center">
    <td colspan="5">مجموعه</td>
    <td><input type="number" name="total_debit" step="0.01"></td>
    <td><input type="number" name="total_credit" step="0.01"></td>
</tr>
</table>

<br>

<!-- Payment Method -->
<table>
<tr class="gray center">
    <td>د تادیی وړ مبلغ</td>
    <td>طریقه تادیه</td>
</tr>
<tr class="center">
    <td><input type="number" name="payable_amount" step="0.01"></td>
    <td>
        <select name="payment_method">
            <option value="bank">بانک</option>
            <option value="cash">نقد</option>
            <option value="lc">LC</option>
            <option value="direct">مستقیم</option>
            <option value="check">چیک</option>
        </select>
    </td>
</tr>
</table>

<br>

<!-- Recipient Info Section -->
<div class="recipient-section" style="border:1px solid #000; padding:15px; margin-bottom:20px;">
    <h3 style="background:#e6e6e6; padding:6px; text-align:center;">د ترلاسه کوونکی اړوند معلومات</h3>
    <div style="margin:10px 0; display:flex; flex-direction:row; align-items:center; gap:10px;">
        <label style="width:250px; text-align:right;">د پیسو د تر لاسه کوونکی نوم:</label>
        <input type="text" name="recipient_name" placeholder="د پیسو د تر لاسه کوونکی نوم" style="flex:1; padding:5px;">
    </div>
    <div style="margin:10px 0; display:flex; flex-direction:row; align-items:center; gap:10px;">
        <label style="width:250px; text-align:right;">د مالې ورکونکی د ترلاسه کوونکی شمېره:</label>
        <input type="text" name="payer_recipient_number" placeholder="د مالې ورکونکی د ترلاسه کوونکی شمېره" style="flex:1; padding:5px;">
    </div>
    <div style="margin:10px 0; display:flex; flex-direction:row; align-items:center; gap:10px;">
        <label style="width:250px; text-align:right;">په مالی سیسټم کې د ترلاسه کوونکی شمېره:</label>
        <input type="text" name="system_recipient_number" placeholder="په مالی سیسټم کې د ترلاسه کوونکی شمېره" style="flex:1; padding:5px;">
    </div>
</div>

<!-- Recipient Bank Info Section -->
<div class="recipient-section" style="border:1px solid #000; padding:15px; margin-bottom:20px;">
    <h3 style="background:#e6e6e6; padding:6px; text-align:center;">دپیسو د تر کوونکی بانکی معلومات</h3>
    <div style="margin:10px 0; display:flex; flex-direction:row; align-items:center; gap:10px;">
        <label style="width:250px; text-align:right;">د پیسو د تر لاسه کوونکی دحساب شمېره:</label>
        <input type="text" name="recipient_bank_account" placeholder="د پیسو د تر لاسه کوونکی دحساب شمېره" style="flex:1; padding:5px;">
    </div>
    <div style="margin:10px 0; display:flex; flex-direction:row; align-items:center; gap:10px;">
        <label style="width:250px; text-align:right;">د انواس/ هویت شمېره:</label>
        <input type="text" name="invoice_id" placeholder="د انواس/ هویت شمېره" style="flex:1; padding:5px;">
    </div>
    <div style="margin:10px 0; display:flex; flex-direction:row; align-items:center; gap:10px;">
        <label style="width:250px; text-align:right;">د بانک نوم:</label>
        <input type="text" name="bank_name" placeholder="د بانک نوم" style="flex:1; padding:5px;">
    </div>
    <div style="margin:10px 0; display:flex; flex-direction:row; align-items:center; gap:10px;">
        <label style="width:250px; text-align:right;">د بانک آدرس:</label>
        <input type="text" name="bank_address" placeholder="د بانک آدرس" style="flex:1; padding:5px;">
    </div>
</div>

<!-- Submit / Print -->
<div class="center">
    <button type="submit">💾 ثبت</button>
    <button type="button" onclick="window.print()">🖨️ چاپ</button>
</div>

</form>
</div>

<?php $conn->close(); ?>
</body>
</html>
