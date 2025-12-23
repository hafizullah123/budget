<?php
session_start();

/* ================= DATABASE CONNECTION ================= */
$conn = new mysqli("localhost","root","","budget1");
if($conn->connect_error){
    die("Connection failed: ".$conn->connect_error);
}

/* ================= HANDLE DELETE OPERATION ================= */
if(isset($_GET['delete_id'])){
    $delete_id = intval($_GET['delete_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete related records first
        $conn->query("DELETE FROM expense_voucher_items WHERE voucher_id = $delete_id");
        $conn->query("DELETE FROM expense_recipients WHERE voucher_id = $delete_id");
        $conn->query("DELETE FROM expense_recipient_banks WHERE voucher_id = $delete_id");
        
        // Delete main voucher
        $conn->query("DELETE FROM expense_vouchers WHERE id = $delete_id");
        
        $conn->commit();
        $_SESSION['message'] = "سند با موفقیت حذف شد!";
        $_SESSION['message_type'] = "success";
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "خطا در حذف سند: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* ================= FETCH VOUCHER FOR EDITING ================= */
$edit_voucher = null;
$edit_items = [];
$edit_recipient = null;
$edit_bank = null;

if(isset($_GET['edit_id'])){
    $edit_id = intval($_GET['edit_id']);
    
    $result = $conn->query("SELECT * FROM expense_vouchers WHERE id = $edit_id");
    if($result->num_rows > 0){
        $edit_voucher = $result->fetch_assoc();
        
        // Fetch related items
        $itemResult = $conn->query("SELECT * FROM expense_voucher_items WHERE voucher_id = $edit_id");
        while($row = $itemResult->fetch_assoc()){
            $edit_items[] = $row;
        }
        
        // Fetch recipient
        $recipientResult = $conn->query("SELECT * FROM expense_recipients WHERE voucher_id = $edit_id");
        if($recipientResult->num_rows > 0){
            $edit_recipient = $recipientResult->fetch_assoc();
        }
        
        // Fetch bank
        $bankResult = $conn->query("SELECT * FROM expense_recipient_banks WHERE voucher_id = $edit_id");
        if($bankResult->num_rows > 0){
            $edit_bank = $bankResult->fetch_assoc();
        }
    }
}

/* ================= HANDLE UPDATE OPERATION ================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])){
    $id = intval($_POST['id']);
    
    // Main voucher data
    $voucher_number = $conn->real_escape_string($_POST['voucher_number']);
    $expense_type = $conn->real_escape_string($_POST['expense_type']);
    $year = $conn->real_escape_string($_POST['year']);
    $voucher_date = $conn->real_escape_string($_POST['voucher_date']);
    $total_debit = floatval($_POST['total_debit']);
    $total_credit = floatval($_POST['total_credit']);
    $payable_amount = floatval($_POST['payable_amount']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // UPDATE existing voucher
        $sql = "UPDATE expense_vouchers SET 
                voucher_number = '$voucher_number',
                expense_type = '$expense_type',
                year = '$year',
                voucher_date = '$voucher_date',
                total_debit = $total_debit,
                total_credit = $total_credit,
                payable_amount = $payable_amount,
                payment_method = '$payment_method'
                WHERE id = $id";
        $conn->query($sql);
        
        // Delete old related records
        $conn->query("DELETE FROM expense_voucher_items WHERE voucher_id = $id");
        $conn->query("DELETE FROM expense_recipients WHERE voucher_id = $id");
        $conn->query("DELETE FROM expense_recipient_banks WHERE voucher_id = $id");
        
        // Insert voucher items
        if(isset($_POST['item_details'])){
            foreach($_POST['item_details'] as $index => $details){
                $details = $conn->real_escape_string($details);
                $general_code = $conn->real_escape_string($_POST['item_general_code'][$index]);
                $sub_code = $conn->real_escape_string($_POST['item_sub_code'][$index]);
                $debit = floatval($_POST['item_debit'][$index]);
                $credit = floatval($_POST['item_credit'][$index]);
                
                $sql = "INSERT INTO expense_voucher_items 
                        (voucher_id, details, general_code, sub_code, debit, credit)
                        VALUES ($id, '$details', '$general_code', '$sub_code', $debit, $credit)";
                $conn->query($sql);
            }
        }
        
        // Insert recipient info
        if(!empty($_POST['recipient_name'])){
            $recipient_name = $conn->real_escape_string($_POST['recipient_name']);
            $payer_recipient_number = $conn->real_escape_string($_POST['payer_recipient_number']);
            $system_recipient_number = $conn->real_escape_string($_POST['system_recipient_number']);
            
            $sql = "INSERT INTO expense_recipients 
                    (voucher_id, recipient_name, payer_recipient_number, system_recipient_number)
                    VALUES ($id, '$recipient_name', '$payer_recipient_number', '$system_recipient_number')";
            $conn->query($sql);
        }
        
        // Insert bank info
        if(!empty($_POST['bank_name'])){
            $bank_name = $conn->real_escape_string($_POST['bank_name']);
            $bank_account = $conn->real_escape_string($_POST['bank_account']);
            $invoice_id = $conn->real_escape_string($_POST['invoice_id']);
            $bank_address = $conn->real_escape_string($_POST['bank_address']);
            
            $sql = "INSERT INTO expense_recipient_banks 
                    (voucher_id, bank_name, bank_account, invoice_id, bank_address)
                    VALUES ($id, '$bank_name', '$bank_account', '$invoice_id', '$bank_address')";
            $conn->query($sql);
        }
        
        $conn->commit();
        $_SESSION['message'] = "سند با موفقیت ویرایش شد!";
        $_SESSION['message_type'] = "success";
        
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "خطا در ویرایش سند: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
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
.voucher-actions{display:flex; gap:10px; padding:10px 15px; background:#f8f9fa; border-top:1px solid #ddd;}
.voucher-content{padding:15px; display:none; border-top:1px solid #ddd;}
.section-title{background:#20c997; color:#fff; padding:5px 10px; font-size:13px; margin-bottom:8px; border-radius:5px; display:inline-block;}

/* Tables inside content */
.voucher-content table{width:100%; border-collapse:collapse; margin-bottom:10px;}
.voucher-content th, .voucher-content td{border:1px solid #ddd; padding:6px; font-size:13px; text-align:center;}
.voucher-content th{background:#ffc107; color:#fff; font-weight:600;}
.voucher-content tr:nth-child(even){background:#f9f9f9;}

/* Buttons */
.btn {padding:8px 15px; border:none; border-radius:5px; cursor:pointer; font-size:13px; font-weight:bold;}
.btn-edit {background:#ffc107; color:#212529;}
.btn-delete {background:#dc3545; color:white;}
.btn-save {background:#007bff; color:white; width:100%; padding:12px;}
.btn-cancel {background:#6c757d; color:white; width:100%; padding:12px; margin-top:10px;}

/* Form Styles for Edit */
.form-container {background:white; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); margin-bottom:30px;}
.form-section {margin-bottom:25px; padding:15px; border:1px solid #ddd; border-radius:5px;}
.form-row {display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap;}
.form-group {flex:1; min-width:200px;}
.form-group label {display:block; margin-bottom:5px; font-weight:bold; color:#555;}
.form-group input, .form-group select {width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:14px;}
.form-group input:focus, .form-group select:focus {outline:none; border-color:#007bff; box-shadow:0 0 0 2px rgba(0,123,255,0.25);}

/* Items table in form */
.items-table {width:100%; border-collapse:collapse; margin-top:10px;}
.items-table th, .items-table td {border:1px solid #ddd; padding:8px; text-align:center;}
.items-table th {background:#17a2b8; color:white;}
.btn-add-item {background:#28a745; color:white; padding:8px 15px; border:none; border-radius:4px; cursor:pointer; margin-top:10px;}

/* Message alerts */
.alert {padding:15px; border-radius:5px; margin-bottom:20px;}
.alert-success {background:#d4edda; color:#155724; border:1px solid #c3e6cb;}
.alert-error {background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;}

/* Modal for delete confirmation */
.modal {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;}
.modal-content {background:white; width:400px; margin:100px auto; padding:20px; border-radius:10px;}
.modal-buttons {display:flex; justify-content:flex-end; gap:10px; margin-top:20px;}

/* Hover effect for card */
.voucher-card:hover{box-shadow:0 6px 18px rgba(0,0,0,0.15);}
</style>
</head>
<body>

<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?>">
        <?= $_SESSION['message'] ?>
        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    </div>
<?php endif; ?>

<h2>لیست سندهای مصرف</h2>
<input type="text" id="searchBox" placeholder="جستجو براساس شماره سند، نوعیت مصرف، سال، نام و کد...">

<?php if(isset($edit_voucher)): ?>
<!-- EDIT FORM -->
<div class="form-container">
    <h3>ویرایش سند شماره <?= htmlspecialchars($edit_voucher['voucher_number']) ?></h3>
    
    <form method="POST" action="">
        <input type="hidden" name="id" value="<?= $edit_voucher['id'] ?>">
        
        <!-- Main Voucher Details -->
        <div class="form-section">
            <h4 class="section-title">مشخصات اصلی سند</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>شماره سند *</label>
                    <input type="text" name="voucher_number" value="<?= htmlspecialchars($edit_voucher['voucher_number']) ?>" required>
                </div>
                <div class="form-group">
                    <label>نوعیت مصرف *</label>
                    <input type="text" name="expense_type" value="<?= htmlspecialchars($edit_voucher['expense_type']) ?>" required>
                </div>
                <div class="form-group">
                    <label>کال *</label>
                    <input type="text" name="year" value="<?= htmlspecialchars($edit_voucher['year']) ?>" required>
                </div>
                <div class="form-group">
                    <label>نېټه *</label>
                    <input type="date" name="voucher_date" value="<?= htmlspecialchars($edit_voucher['voucher_date']) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>مجموعه ډبیټ</label>
                    <input type="number" step="0.01" name="total_debit" value="<?= htmlspecialchars($edit_voucher['total_debit']) ?>">
                </div>
                <div class="form-group">
                    <label>مجموعه کریډیټ</label>
                    <input type="number" step="0.01" name="total_credit" value="<?= htmlspecialchars($edit_voucher['total_credit']) ?>">
                </div>
                <div class="form-group">
                    <label>د تادیی وړ مبلغ</label>
                    <input type="number" step="0.01" name="payable_amount" value="<?= htmlspecialchars($edit_voucher['payable_amount']) ?>">
                </div>
                <div class="form-group">
                    <label>طریقه تادیه</label>
                    <select name="payment_method">
                        <option value="نقد" <?= $edit_voucher['payment_method'] == 'نقد' ? 'selected' : '' ?>>نقد</option>
                        <option value="بانک" <?= $edit_voucher['payment_method'] == 'بانک' ? 'selected' : '' ?>>بانک</option>
                        <option value="چک" <?= $edit_voucher['payment_method'] == 'چک' ? 'selected' : '' ?>>چک</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Voucher Items -->
        <div class="form-section">
            <h4 class="section-title">تفصیلات</h4>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>تفصیلات</th>
                        <th>عمومي کوډ</th>
                        <th>فرعی کوډ</th>
                        <th>ډبیټ</th>
                        <th>کریډیټ</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody id="items-tbody">
                    <?php foreach($edit_items as $index => $item): ?>
                    <tr>
                        <td><input type="text" name="item_details[]" value="<?= htmlspecialchars($item['details']) ?>" required></td>
                        <td><input type="text" name="item_general_code[]" value="<?= htmlspecialchars($item['general_code']) ?>" required></td>
                        <td><input type="text" name="item_sub_code[]" value="<?= htmlspecialchars($item['sub_code']) ?>" required></td>
                        <td><input type="number" step="0.01" name="item_debit[]" value="<?= htmlspecialchars($item['debit']) ?>"></td>
                        <td><input type="number" step="0.01" name="item_credit[]" value="<?= htmlspecialchars($item['credit']) ?>"></td>
                        <td><button type="button" onclick="removeItem(this)" class="btn btn-delete">حذف</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" onclick="addItem()" class="btn btn-add-item">➕ اضافه کردن تفصیل</button>
        </div>
        
        <!-- Recipient Info -->
        <div class="form-section">
            <h4 class="section-title">د ترلاسه کوونکی</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>نام</label>
                    <input type="text" name="recipient_name" value="<?= htmlspecialchars($edit_recipient['recipient_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>شماره</label>
                    <input type="text" name="payer_recipient_number" value="<?= htmlspecialchars($edit_recipient['payer_recipient_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>سیستم شماره</label>
                    <input type="text" name="system_recipient_number" value="<?= htmlspecialchars($edit_recipient['system_recipient_number'] ?? '') ?>">
                </div>
            </div>
        </div>
        
        <!-- Bank Info -->
        <div class="form-section">
            <h4 class="section-title">بانک</h4>
            <div class="form-row">
                <div class="form-group">
                    <label>نام بانک</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($edit_bank['bank_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>شماره حساب</label>
                    <input type="text" name="bank_account" value="<?= htmlspecialchars($edit_bank['bank_account'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>انوایس</label>
                    <input type="text" name="invoice_id" value="<?= htmlspecialchars($edit_bank['invoice_id'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>آدرس</label>
                    <input type="text" name="bank_address" value="<?= htmlspecialchars($edit_bank['bank_address'] ?? '') ?>">
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-save">💾 ذخیره تغییرات</button>
        <button type="button" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] ?>'" class="btn-cancel">
            ❌ لغو
        </button>
    </form>
</div>
<?php endif; ?>

<!-- VOUCHER LISTING -->
<div id="voucherContainer">
<?php foreach($vouchers as $vid => $voucher): ?>
<div class="voucher-card">
    <div class="voucher-header" onclick="toggleCard(this)">
        <div>شماره سند: <?= htmlspecialchars($voucher['voucher_number']) ?></div>
        <div>نوعیت مصرف: <?= htmlspecialchars($voucher['expense_type']) ?></div>
        <div>کال: <?= htmlspecialchars($voucher['year']) ?></div>
        <div>نېټه: <?= htmlspecialchars($voucher['voucher_date']) ?></div>
    </div>
    
    <div class="voucher-actions">
        <button onclick="window.location.href='?edit_id=<?= $vid ?>'" class="btn btn-edit">✏️ ویرایش</button>
        <button onclick="confirmDelete(<?= $vid ?>)" class="btn btn-delete">🗑️ حذف</button>
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

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3>تأیید حذف</h3>
        <p>آیا از حذف این سند اطمینان دارید؟ این عمل غیرقابل بازگشت است.</p>
        <div class="modal-buttons">
            <button onclick="cancelDelete()" class="btn btn-edit">لغو</button>
            <button onclick="proceedDelete()" class="btn btn-delete">حذف</button>
        </div>
    </div>
</div>

<script>
let deleteId = null;

// Toggle card content
function toggleCard(el){
    let content = el.nextElementSibling.nextElementSibling;
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

// Add new item row to form
function addItem(){
    const tbody = document.getElementById('items-tbody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" name="item_details[]" required></td>
        <td><input type="text" name="item_general_code[]" required></td>
        <td><input type="text" name="item_sub_code[]" required></td>
        <td><input type="number" step="0.01" name="item_debit[]"></td>
        <td><input type="number" step="0.01" name="item_credit[]"></td>
        <td><button type="button" onclick="removeItem(this)" class="btn btn-delete">حذف</button></td>
    `;
    tbody.appendChild(newRow);
}

// Remove item row from form
function removeItem(button){
    button.closest('tr').remove();
}

// Delete confirmation
function confirmDelete(id){
    deleteId = id;
    document.getElementById('deleteModal').style.display = 'block';
}

function cancelDelete(){
    deleteId = null;
    document.getElementById('deleteModal').style.display = 'none';
}

function proceedDelete(){
    if(deleteId){
        window.location.href = '?delete_id=' + deleteId;
    }
}

// Close modal when clicking outside
window.onclick = function(event){
    const modal = document.getElementById('deleteModal');
    if(event.target == modal){
        cancelDelete();
    }
}
</script>

</body>
</html>