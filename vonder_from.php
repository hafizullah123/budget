<?php
/* ================= SESSION & DATABASE CONNECTION ================= */
session_start();

$conn = new mysqli("localhost","root","","budget1");
if($conn->connect_error){
    die("Connection failed: ".$conn->connect_error);
}

/* ================= CLEAR SESSION MESSAGES ON PAGE LOAD ================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_POST)) {
    unset($_SESSION['success']);
    unset($_SESSION['error']);
    unset($_SESSION['debug_info']);
}

/* ================= HANDLE SUBMIT ================= */
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
$debug_info = $_SESSION['debug_info'] ?? '';

unset($_SESSION['success'], $_SESSION['error'], $_SESSION['debug_info']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        $budget_errors = [];
        $code_amounts = [];
        $expense_type = $_POST['expense_type'] ?? '';
        
        // Validate budget limits
        foreach ($_POST['details'] as $i => $detail) {
            if (trim($detail) === '') continue;

            $debit  = $_POST['debit'][$i] ?? 0;
            $credit = $_POST['credit'][$i] ?? 0;
            $general_code = $_POST['general_code'][$i] ?? '';
            
            if ($general_code && $debit > 0) {
                // CORRECTED: Use general_code and sub_code columns
                $check_stmt = $conn->prepare("
                    SELECT budget, actual 
                    FROM budget_details 
                    WHERE general_code = ? AND sub_code = ?
                ");
                $check_stmt->bind_param("ss", $general_code, $expense_type);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $row = $check_result->fetch_assoc();
                    $remaining_budget = $row['budget'];
                    
                    if ($debit > $remaining_budget) {
                        $budget_errors[] = "مبلغ مصرف ($debit) برای کوډ $general_code از بودجه باقیمانده ($remaining_budget) بیشتر است!";
                    } else {
                        // Use a composite key to track amounts per general_code and expense_type
                        $key = $general_code . '_' . $expense_type;
                        if (!isset($code_amounts[$key])) {
                            $code_amounts[$key] = [
                                'general_code' => $general_code,
                                'expense_type' => $expense_type,
                                'amount' => 0
                            ];
                        }
                        $code_amounts[$key]['amount'] += $debit;
                    }
                } else {
                    $budget_errors[] = "کوډ $general_code برای باب $expense_type در سیستم بودجه موجود نیست!";
                }
                $check_stmt->close();
            }
        }
        
        if (!empty($budget_errors)) {
            throw new Exception(implode("<br>", $budget_errors));
        }

        /* ---------- expense_vouchers ---------- */
        $stmt = $conn->prepare("
            INSERT INTO expense_vouchers
            (expense_type_code, expense_type_desc, voucher_number, voucher_date, year,
             system_number, system_date, sgtas_number, scan_number,
             asaar, currency, admin_code, total_debit, total_credit, payable_amount, payment_method)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $total_debit    = $_POST['total_debit'] ?? 0;
        $total_credit   = $_POST['total_credit'] ?? 0;
        $payable_amount = $_POST['payable_amount'] ?? 0;
        $currency = 'AFN';
        $admin_code = '194000';

        $stmt->bind_param(
            "isssssssssssddds",
            $_POST['expense_type_code'],
            $_POST['expense_type_desc'],
            $_POST['voucher_number'],
            $_POST['voucher_date'],
            $_POST['year'],
            $_POST['system_number'],
            $_POST['system_date'],
            $_POST['sgtas_number'],
            $_POST['scan_number'],
            $_POST['asaar'],
            $currency,
            $admin_code,
            $total_debit,
            $total_credit,
            $payable_amount,
            $_POST['payment_method']
        );
        $stmt->execute();
        $voucher_id = $conn->insert_id;
        $stmt->close();

        /* ---------- expense_voucher_items ---------- */
        $stmt = $conn->prepare("
            INSERT INTO expense_voucher_items
            (voucher_id, details, general_code, sub_code, debit, credit)
            VALUES (?,?,?,?,?,?)
        ");
        
        foreach ($_POST['details'] as $i => $detail) {
            if (trim($detail) === '') continue;

            $debit  = $_POST['debit'][$i] ?? 0;
            $credit = $_POST['credit'][$i] ?? 0;
            $general_code = $_POST['general_code'][$i];
            $sub_code = $_POST['sub_code'][$i];

            $stmt->bind_param(
                "isssdd",
                $voucher_id,
                $detail,
                $general_code,
                $sub_code,
                $debit,
                $credit
            );
            $stmt->execute();
        }
        $stmt->close();

        /* ---------- expense_recipients ---------- */
        if (!empty($_POST['recipient_name'])) {
            $stmt = $conn->prepare("
                INSERT INTO expense_recipients
                (voucher_id, recipient_name, payer_recipient_number, system_recipient_number)
                VALUES (?,?,?,?)
            ");
            $stmt->bind_param(
                "isss",
                $voucher_id,
                $_POST['recipient_name'],
                $_POST['payer_recipient_number'],
                $_POST['system_recipient_number']
            );
            $stmt->execute();
            $stmt->close();
        }

        /* ---------- expense_recipient_banks ---------- */
        if (!empty($_POST['recipient_bank_account'])) {
            $stmt = $conn->prepare("
                INSERT INTO expense_recipient_banks
                (voucher_id, bank_account, invoice_id, bank_name, bank_address)
                VALUES (?,?,?,?,?)
            ");
            $stmt->bind_param(
                "issss",
                $voucher_id,
                $_POST['recipient_bank_account'],
                $_POST['invoice_id'],
                $_POST['bank_name'],
                $_POST['bank_address']
            );
            $stmt->execute();
            $stmt->close();
        }

        /* ---------- UPDATE BUDGET_DETAILS ---------- */
        $debug_output = "<br><strong>Debug Info:</strong><br>";
        $debug_output .= "Expense Type (bab): " . htmlspecialchars($expense_type) . "<br>";
        
        $total_updated = 0;
        foreach ($code_amounts as $key => $data) {
            $general_code = $data['general_code'];
            $expense_type = $data['expense_type'];
            $amount = $data['amount'];
            
            if ($amount > 0) {
                // CORRECTED: Use general_code and sub_code columns
                $check_stmt = $conn->prepare("
                    SELECT budget, actual 
                    FROM budget_details 
                    WHERE general_code = ? AND sub_code = ? 
                    FOR UPDATE
                ");
                $check_stmt->bind_param("ss", $general_code, $expense_type);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $row = $check_result->fetch_assoc();
                    $current_budget = $row['budget'];
                    $current_actual = $row['actual'];
                    
                    $new_budget = $current_budget - $amount;
                    $new_actual = $current_actual + $amount;
                    $original_budget = $current_budget + $current_actual; // This gives the original total
                    
                    if ($original_budget > 0) {
                        $new_percent = min(100, ($new_actual / $original_budget) * 100);
                        $new_percent = round($new_percent, 2);
                    } else {
                        $new_percent = 0;
                    }
                    
                    // CORRECTED: Update query with proper column names
                    $update_stmt = $conn->prepare("
                        UPDATE budget_details 
                        SET budget = ?, actual = ?, percent = ?
                        WHERE general_code = ? AND sub_code = ?
                    ");
                    
                    $update_stmt->bind_param("dddss", 
                        $new_budget, 
                        $new_actual, 
                        $new_percent, 
                        $general_code, 
                        $expense_type
                    );
                    
                    if ($update_stmt->execute()) {
                        $total_updated++;
                        $debug_output .= "Updated: $general_code (Type: $expense_type) - ";
                        $debug_output .= "Old Budget: $current_budget, New Budget: $new_budget, ";
                        $debug_output .= "Amount Used: $amount<br>";
                    } else {
                        $debug_output .= "Failed to update: $general_code (Type: $expense_type) - ";
                        $debug_output .= "Error: " . $update_stmt->error . "<br>";
                    }
                    $update_stmt->close();
                } else {
                    $debug_output .= "Not found in budget_details: $general_code (Type: $expense_type)<br>";
                }
                $check_stmt->close();
            }
        }

        $conn->commit();
        
        $_SESSION['success'] = "✅ سند په بریالیتوب ثبت شو";
        if ($total_updated > 0) {
            $_SESSION['success'] .= "<br>✅ بودیجې تازه شوې ($total_updated کوډونه)";
        }
        
        // Store debug info in session for display
        if (!empty($debug_output)) {
            $_SESSION['debug_info'] = $debug_output;
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "❌ خطا: ".$e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

/* ================= FETCH BAB FROM DATABASE ================= */
// SIMPLE QUERY: Get all distinct sub_code values from budget_details
$bab_query = "SELECT DISTINCT sub_code FROM budget_details ORDER BY sub_code ASC";
$babResult = $conn->query($bab_query);

// Debug: Check query result
$bab_options = '';
$debug_info = '';

if (!$babResult) {
    // Query failed
    $debug_info = "Query error: " . $conn->error;
    $bab_options = '<option value="">خطا در دریافت داده ها</option>';
} elseif ($babResult->num_rows > 0) {
    // Query successful, we have data
    while($row = $babResult->fetch_assoc()) {
        $bab_value = htmlspecialchars($row['sub_code']);
        $bab_options .= '<option value="' . $bab_value . '">' . $bab_value . '</option>';
    }
} else {
    // No data found
    $bab_options = '<option value="">-- هیچ داده‌ای یافت نشد --</option>';
    $debug_info = "هیچ داده‌ای در جدول budget_details یافت نشد.";
}

// Also fetch codes for suggestions
$codeResult = $conn->query("
    SELECT general_code, sub_code, budget as remaining_budget, actual as spent,
           (budget + actual) as original_budget,
           CASE 
               WHEN (budget + actual) > 0 THEN ROUND((actual / (budget + actual)) * 100, 2)
               ELSE 0 
           END as current_percent
    FROM budget_details 
    ORDER BY general_code ASC, sub_code ASC
");

$all_codes = [];
$all_general_codes = []; // For datalist

if ($codeResult) {
    while($row = $codeResult->fetch_assoc()){
        $general_code = $row['general_code'];
        $sub_code = $row['sub_code'];
        
        // Create a unique composite key for each general_code and sub_code combination
        $composite_key = $general_code . '_' . $sub_code;
        
        $all_codes[$composite_key] = [
            'general_code' => $general_code,
            'sub_code' => $sub_code,
            'remaining_budget' => $row['remaining_budget'],
            'spent' => $row['spent'],
            'original_budget' => $row['original_budget'],
            'current_percent' => $row['current_percent'],
            'bab' => $sub_code
        ];
        
        // Add to general codes list for datalist
        if (!in_array($general_code, $all_general_codes)) {
            $all_general_codes[] = $general_code;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ویندر فورم</title>
<style>
body{font-family:'Segoe UI', Tahoma, Arial; background:#f2f5f7; padding:20px;}
.page{width:90%; max-width:1200px; margin:auto; padding:20px; border:2px solid #000; border-radius:10px; background:#fff;}
table{width:100%; border-collapse:collapse; font-size:14px; margin-bottom:10px;}
td, th{border:1px solid #000; padding:6px; vertical-align:middle;}
.center{text-align:center;}
.right{text-align:right;}
.left{text-align:left;}
.gray{background:#e6e6e6; font-weight:bold; color:#333;}
input[type=text], input[type=number], input[type=date], select{
    width:100%; padding:6px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px;
}
select.scrollable {max-height:150px; overflow-y:auto; display:block;}
button{padding:8px 15px; cursor:pointer; border:none; background:#007bff; color:#fff; border-radius:5px;}
button:hover{background:#0056b3;}
.toggle-section{cursor:pointer; background:#f0f0f0; padding:8px; border-radius:5px; margin-bottom:5px; font-weight:bold;}
.toggle-section:hover{background:#e0e0e0;}
.hidden{display:none;}
.success-message{color:green; font-weight:bold; text-align:center; margin-top:10px; padding:10px; background:#e6ffe6; border-radius:5px; position:relative;}
.error-message{color:red; font-weight:bold; text-align:center; margin-top:10px; padding:10px; background:#ffe6e6; border-radius:5px; position:relative;}
.debug-info{background:#f8f9fa; border:1px solid #ddd; padding:10px; margin:10px 0; font-size:12px; color:#666; position:relative;}
.close-btn{position:absolute; top:5px; left:10px; background:#ccc; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; font-size:12px; line-height:1;}
.close-btn:hover{background:#999; color:white;}
.message-hiding{opacity:0.7;}
.bab-info{font-size:11px; color:#666; margin-top:5px;}
.code-suggestion{font-size:11px; color:#666; margin-top:2px; padding:3px; background:#f8f9fa; border-radius:3px;}
.budget-ok{color:green;}
.budget-warning{color:orange;}
.budget-error{color:red;}
.budget-exhausted{color:red; font-weight:bold;}
@media print{button{display:none;} .debug-info{display:none;} .close-btn{display:none;}}
</style>
<script>
// Auto-hide messages after 3 seconds
function autoHideMessages() {
    const messages = document.querySelectorAll('.success-message, .error-message, .debug-info');
    
    messages.forEach(message => {
        const closeBtn = document.createElement('button');
        closeBtn.className = 'close-btn';
        closeBtn.innerHTML = '×';
        closeBtn.title = 'بستن';
        closeBtn.onclick = function() {
            message.style.display = 'none';
        };
        message.style.position = 'relative';
        message.appendChild(closeBtn);
        
        setTimeout(() => {
            message.classList.add('message-hiding');
        }, 2500);
        
        setTimeout(() => {
            message.style.display = 'none';
        }, 3000);
    });
}

document.addEventListener('DOMContentLoaded', autoHideMessages);

function toggleSection(id){
    const el = document.getElementById(id);
    el.classList.toggle('hidden');
}

function addRow(){
    let t=document.querySelector("#voucherItems tbody");
    let r=t.insertRow(-1);
    r.innerHTML=`<td><input name="details[]"></td>
                 <td>
                    <input name="general_code[]" list="codeList" oninput="showCodeBudgetInfo(this)">
                    <div class="code-suggestion"></div>
                 </td>
                 <td><input name="sub_code[]"></td>
                 <td><input type="number" step="0.01" name="debit[]" class="debit-input" oninput="checkBudgetLimit(this)"></td>
                 <td><input type="number" step="0.01" name="credit[]" class="credit-input"></td>`;
    
    r.querySelector('.debit-input').addEventListener('input', calculateTotals);
    r.querySelector('.credit-input').addEventListener('input', calculateTotals);
}

function showCodeBudgetInfo(input) {
    const suggestionDiv = input.nextElementSibling;
    const code = input.value.trim();
    const expenseType = document.querySelector('select[name="expense_type"]').value;
    
    if (code.length > 0 && expenseType) {
        const codeData = <?php echo json_encode($all_codes); ?>;
        
        // Look for the code with the current expense type
        const compositeKey = code + '_' + expenseType;
        
        if (codeData[compositeKey]) {
            const data = codeData[compositeKey];
            const remaining = data.remaining_budget;
            const spent = data.spent;
            const original = data.original_budget;
            const currentPercent = data.current_percent || 0;
            const bab = data.sub_code;
            
            suggestionDiv.innerHTML = `
                <div>
                    <span class="budget-ok">بودجه اصلی: ${original.toLocaleString()}</span><br>
                    <span>مصرف شده: ${spent.toLocaleString()}</span><br>
                    <span>بودجه باقیمانده: ${remaining.toLocaleString()}</span><br>
                    <span>درصد مصرف: ${currentPercent}%</span><br>
                    <span>باب: ${bab}</span>
                </div>
            `;
            
            if (remaining <= 0) {
                suggestionDiv.innerHTML += '<span class="budget-exhausted">(بودجه تمام شده!)</span>';
            } else if (remaining < (original * 0.1)) {
                suggestionDiv.innerHTML += '<span class="budget-warning">(بودجه در حال اتمام!)</span>';
            }
        } else {
            // Check if the code exists for another bab
            let codeExistsForOtherBab = false;
            for (let key in codeData) {
                if (codeData[key].general_code === code) {
                    codeExistsForOtherBab = true;
                    break;
                }
            }
            
            if (codeExistsForOtherBab) {
                suggestionDiv.innerHTML = '<span class="budget-error">این کوډ برای باب انتخاب شده معتبر نیست!</span>';
            } else {
                suggestionDiv.innerHTML = '<span class="budget-error">این کوډ در سیستم بودجه موجود نیست!</span>';
            }
        }
    } else if (code.length > 0) {
        suggestionDiv.innerHTML = '<span style="color: #999;">ابتدا نوعیت مصرف را انتخاب کنید</span>';
    } else {
        suggestionDiv.innerHTML = "";
    }
}

function checkBudgetLimit(input) {
    const row = input.closest('tr');
    const codeInput = row.querySelector('input[name="general_code[]"]');
    const expenseType = document.querySelector('select[name="expense_type"]').value;
    const amount = parseFloat(input.value) || 0;
    
    if (codeInput.value && expenseType) {
        const codeData = <?php echo json_encode($all_codes); ?>;
        const compositeKey = codeInput.value + '_' + expenseType;
        const data = codeData[compositeKey];
        
        if (data && data.sub_code === expenseType) {
            const remaining = data.remaining_budget;
            
            if (amount > remaining) {
                input.style.borderColor = '#dc3545';
                input.style.backgroundColor = '#ffe6e6';
                input.setCustomValidity(`مبلغ از بودجه باقیمانده (${remaining.toLocaleString()}) بیشتر است!`);
            } else {
                input.style.borderColor = '#28a745';
                input.style.backgroundColor = '#e6ffe6';
                input.setCustomValidity('');
            }
        } else {
            input.style.borderColor = '#dc3545';
            input.style.backgroundColor = '#ffe6e6';
            input.setCustomValidity('کوډ انتخاب شده برای این باب معتبر نیست!');
        }
    }
}

function calculateTotals() {
    let totalDebit = 0;
    let totalCredit = 0;
    
    document.querySelectorAll('input[name="debit[]"]').forEach(input => {
        totalDebit += parseFloat(input.value) || 0;
    });
    
    document.querySelectorAll('input[name="credit[]"]').forEach(input => {
        totalCredit += parseFloat(input.value) || 0;
    });
    
    const totalDebitInput = document.querySelector('input[name="total_debit"]');
    const totalCreditInput = document.querySelector('input[name="total_credit"]');
    const payableInput = document.querySelector('input[name="payable_amount"]');
    
    if (totalDebitInput) totalDebitInput.value = totalDebit.toFixed(2);
    if (totalCreditInput) totalCreditInput.value = totalCredit.toFixed(2);
    if (payableInput) payableInput.value = totalDebit.toFixed(2);
}

function validateForm() {
    let isValid = true;
    const expenseType = document.querySelector('select[name="expense_type"]').value;
    const codeData = <?php echo json_encode($all_codes); ?>;
    
    if (!expenseType) {
        alert('لطفاً نوعیت مصرف را انتخاب کنید!');
        return false;
    }
    
    document.querySelectorAll('input[name="general_code[]"]').forEach((codeInput, index) => {
        const detail = document.querySelectorAll('input[name="details[]"]')[index].value;
        const debitInput = document.querySelectorAll('input[name="debit[]"]')[index];
        const debit = parseFloat(debitInput.value) || 0;
        
        if (detail.trim() && debit > 0) {
            const code = codeInput.value;
            
            if (!code) {
                alert(`ردیف ${index + 1}: کوډ عمومی را وارد کنید!`);
                isValid = false;
                return false;
            }
            
            const compositeKey = code + '_' + expenseType;
            
            if (codeData[compositeKey]) {
                if (codeData[compositeKey].sub_code !== expenseType) {
                    alert(`ردیف ${index + 1}: کوډ "${code}" برای باب "${expenseType}" معتبر نیست!`);
                    isValid = false;
                    return false;
                }
                
                const remaining = codeData[compositeKey].remaining_budget;
                if (debit > remaining) {
                    alert(`ردیف ${index + 1}: مبلغ مصرف (${debit.toLocaleString()}) از بودجه باقیمانده (${remaining.toLocaleString()}) بیشتر است!`);
                    isValid = false;
                    return false;
                }
            } else {
                // Check if code exists for other bab
                let codeExists = false;
                for (let key in codeData) {
                    if (codeData[key].general_code === code) {
                        codeExists = true;
                        break;
                    }
                }
                
                if (codeExists) {
                    alert(`ردیف ${index + 1}: کوډ "${code}" برای باب "${expenseType}" معتبر نیست!`);
                } else {
                    alert(`ردیف ${index + 1}: کوډ "${code}" در سیستم بودجه موجود نیست!`);
                }
                isValid = false;
                return false;
            }
        }
    });
    
    if (isValid) {
        return confirm('آیا از ثبت سند اطمینان دارید؟');
    }
    
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('select[name="expense_type"]').addEventListener('change', function() {
        document.querySelectorAll('input[name="general_code[]"]').forEach(input => {
            if (input.value) {
                showCodeBudgetInfo(input);
            }
        });
    });
    
    function attachEvents() {
        document.querySelectorAll('input[name="debit[]"], input[name="credit[]"]').forEach(input => {
            input.removeEventListener('input', calculateTotals);
            input.addEventListener('input', calculateTotals);
        });
        
        document.querySelectorAll('input[name="general_code[]"]').forEach(input => {
            input.addEventListener('input', function() {
                showCodeBudgetInfo(this);
                const row = this.closest('tr');
                const debitInput = row.querySelector('input[name="debit[]"]');
                if (debitInput && debitInput.value) {
                    checkBudgetLimit(debitInput);
                }
            });
        });
    }
    
    attachEvents();
    calculateTotals();
    
    document.querySelectorAll('input[name="general_code[]"]').forEach(input => {
        if (input.value) {
            showCodeBudgetInfo(input);
        }
    });
});
</script>
</head>
<body>

<div class="page">
<form method="POST" onsubmit="return validateForm()">

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
    
<td class="right">کد نوعیت مصرف</td><td colspan="6"><input name="expense_type_code" ></td>
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

</div>

<?php $conn->close(); ?>
</body>
</html>