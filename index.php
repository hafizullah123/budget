<?php
require_once 'config/config.php';
require_once 'init_session.php';
require_once 'budget_functions.php';

$conn = getDatabaseConnection();

// Fetch data for dropdowns
list($all_codes, $all_general_codes) = fetchBudgetCodes($conn);
list($bab_options, $debug_info) = fetchBabOptions($conn);

$conn->close();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ویندر فورم</title>
<link rel="stylesheet" href="style.css">
<script src="form_validation.js" defer></script>
<script>
    // Make code data available to JavaScript
    window.codeData = <?php echo json_encode($all_codes); ?>;
</script>
</head>
<body>

<div class="page">
<form method="POST" action="submit_voucher.php" onsubmit="return validateForm()">

<!-- HEADER -->
<table class="no-border">
<tr>
<td class="center"><strong>د افغانستان اسلامی امارت</strong><br>امارتی شرکتونو لوی ریاست<br>
مالی او اداری معینیت<br>مالی او حساسبی ریاست <br>
د محاسبی او معاشاتو آمریت</td>
</tr>
</tr>
</table>

<!-- EXPENSE TYPE -->
<table>
<tr class="center gray"><td>نوعیت مصرف (باب)</td><td>توضیح نوعیت مصرف</td></tr>
<tr class="center">
<td>
<select name="expense_type" class="scrollable" required>
<option value="">-- د مصرف ډول انتخاب کړئ --</option>
<?php echo $bab_options; ?>
</select>
</td>
<td>
<select name="expense_type_desc" required>
<option value="">-- انتخاب کنید --</option>
<option value="عملیات">عملیات</option>
<option value="توسعه">توسعه</option>
<option value="نگهداری">نگهداری</option>
<option value="خرید">خرید</option>
<option value="سایر">سایر</option>
</select>
</td>
</tr>
</table>

<br>

<!-- VOUCHER & SYSTEM INFO -->
<table>
<tr class="gray center">
<td colspan="3">معلومات سند</td>
<td colspan="4">معلومات سیسټم</td>
</tr>
<tr>
<td class="right">سند شمېره</td><td colspan="2"><input name="voucher_number" required></td>
<td class="right">مالی سیسټم شمېره</td><td colspan="3"><input name="system_number"></td>
</tr>
<tr>
<td class="right">نېټه</td><td colspan="2"><input type="date" name="voucher_date" required></td>
<td class="right">نېټه</td><td colspan="3"><input type="date" name="system_date"></td>
</tr>
<tr>
<td class="right">کال</td><td colspan="2"><input name="year" required></td>
<td class="right">د سګټاس شمېره</td><td colspan="3"><input name="sgtas_number"></td>
</tr>
<tr>
<td colspan="3"></td>
<td class="right">د سکن شمېره</td><td colspan="3"><input name="scan_number"></td>
</tr>
</table>

<br>

<!-- ASAR -->
<table>
<tr class="gray center"><td>اسعار</td><td>واحد پول</td><td>اداری کوډ</td></tr>
<tr class="center">
<td><input name="asaar"></td>
<td>افغانی</td>
<td>194000</td>
</tr>
</table>

<br>

<!-- VOUCHER ITEMS -->
<table>
<thead>
<tr class="gray center">
<th>تفصیلات</th><th>عمومي کوډ</th><th>فرعی کوډ</th><th>ډبیټ</th><th>کریډیټ</th>
</tr>
</thead>
<tbody id="voucherItems">
<tr>
<td><input name="details[]" required></td>
<td>
    <input name="general_code[]" list="codeList" required>
    <div class="code-suggestion"></div>
</td>
<td><input name="sub_code[]"></td>
<td><input type="number" step="0.01" name="debit[]" class="debit-input" required></td>
<td><input type="number" step="0.01" name="credit[]" class="credit-input"></td>
</tr>
</tbody>
</table>

<datalist id="codeList">
<?php foreach($all_general_codes as $code): ?>
<option value="<?= htmlspecialchars($code) ?>">
<?php endforeach; ?>
</datalist>

<button type="button" onclick="addRow()">➕ قطار زیات کړئ</button>

<br>

<!-- TOTALS -->
<table>
<tr class="gray center">
<td colspan="5">مجموعه</td>
<td><input type="number" step="0.01" name="total_debit" readonly></td>
<td><input type="number" step="0.01" name="total_credit" readonly></td>
</tr>
</table>

<br>

<!-- PAYMENT -->
<table>
<tr class="gray center"><td>د تادیی وړ مبلغ</td><td>طریقه تادیه</td></tr>
<tr class="center">
<td><input type="number" step="0.01" name="payable_amount" readonly></td>
<td>
<select name="payment_method" required>
<option value="">-- د تادیې طریقه انتخاب کړئ --</option>
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

<!-- COLLAPSIBLE RECIPIENT -->
<div class="toggle-section center" onclick="toggleSection('recipientSection')">
د ترلاسه کوونکی اړوند معلومات
</div>
<div id="recipientSection" class="hidden" style="border:1px solid #000; padding:15px;">
<table style="width:100%; border-collapse:collapse;">
<tr><td>نوم</td><td><input name="recipient_name"></td></tr>
<tr><td>شمېره</td><td><input name="payer_recipient_number"></td></tr>
<tr><td>سیسټم شمېره</td><td><input name="system_recipient_number"></td></tr>
</table>
</div>

<br>

<!-- COLLAPSIBLE BANK -->
<div class="toggle-section center" onclick="toggleSection('bankSection')">
بانکي معلومات
</div>
<div id="bankSection" class="hidden" style="border:1px solid #000; padding:15px;">
<table style="width:100%; border-collapse:collapse;">
<tr><td>حساب</td><td><input name="recipient_bank_account"></td></tr>
<tr><td>انوایس</td><td><input name="invoice_id"></td></tr>
<tr><td>بانک</td><td><input name="bank_name"></td></tr>
<tr><td>آدرس</td><td><input name="bank_address"></td></tr>
</table>
</div>

<br>

<div class="center">
<button type="submit">💾 ثبت</button>
<button type="button" onclick="window.print()">🖨️ چاپ</button>
</div>

</form>

<?php if($success): ?>
<div class="success-message">
    <?= $success ?>
</div>
<?php endif; ?>

<?php if(isset($error)): ?>
<div class="error-message">
    <?= $error ?>
</div>
<?php endif; ?>

<?php if(!empty($debug_info)): ?>
<div class="debug-info">
    <strong>Debug Information:</strong><br>
    <?= $debug_info ?>
</div>
<?php endif; ?>

<!-- FOOTER -->
<div class="footer">
    <div class="footer-left">
        <h3>جوړ وونکی </h3>
        <h5>حواله جاتو مامور</h5><br>

        <h3>تا ییدوونکی </h3>
        <h5>مالی او حسابی ریس</h5><br>
    </div>
    <div class="footer-right">
        <h3>تصحیح کوونکی  </h3>
        <h5>محاسبه او معاشاتو آمر</h5><br>

        <h3>منظور کوونکی </h3>
        <h5>دامارتی شرکتونو د لوی ریاست مالی صلاحیت دار</h5><br>
    </div>
</div>

</div>

</body>
</html>