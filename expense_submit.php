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
        
        // Validate budget limits for each row
        foreach ($_POST['details'] as $i => $detail) {
            if (trim($detail) === '') continue;

            $general_code = $_POST['general_code'][$i] ?? '';
            $sub_codes_string = $_POST['sub_code'][$i] ?? '';
            $debits_string = $_POST['debit_per_sub_code'][$i] ?? '';
            $credits_string = $_POST['credit_per_sub_code'][$i] ?? '';
            
            if ($general_code && ($debits_string || $credits_string)) {
                // Split multiple sub-codes by newline or comma
                $sub_codes_array = preg_split('/[\n,]+/', $sub_codes_string);
                $sub_codes_array = array_map('trim', $sub_codes_array);
                $sub_codes_array = array_filter($sub_codes_array, function($val) {
                    return $val !== '';
                });
                
                if (empty($sub_codes_array)) {
                    $budget_errors[] = "ردیف " . ($i+1) . ": لطفاً حداقل یک فرعی کوډ وارد کنید!";
                    continue;
                }
                
                // Split debit amounts by newline or comma
                $debits_array = [];
                if (!empty(trim($debits_string))) {
                    $debits_array = preg_split('/[\n,]+/', $debits_string);
                    $debits_array = array_map(function($val) {
                        $trimmed = trim($val);
                        return is_numeric($trimmed) ? floatval($trimmed) : 0;
                    }, $debits_array);
                    $debits_array = array_filter($debits_array, function($val) {
                        return $val !== '';
                    });
                }
                
                // Split credit amounts by newline or comma
                $credits_array = [];
                if (!empty(trim($credits_string))) {
                    $credits_array = preg_split('/[\n,]+/', $credits_string);
                    $credits_array = array_map(function($val) {
                        $trimmed = trim($val);
                        return is_numeric($trimmed) ? floatval($trimmed) : 0;
                    }, $credits_array);
                    $credits_array = array_filter($credits_array, function($val) {
                        return $val !== '';
                    });
                }
                
                // Check if number of amounts matches number of sub-codes
                if (!empty($debits_array) && count($debits_array) != count($sub_codes_array)) {
                    $budget_errors[] = "ردیف " . ($i+1) . ": تعداد مقادیر ډبیټ (" . count($debits_array) . ") با تعداد فرعی کوډها (" . count($sub_codes_array) . ") مطابقت ندارد!";
                    continue;
                }
                
                if (!empty($credits_array) && count($credits_array) != count($sub_codes_array)) {
                    $budget_errors[] = "ردیف " . ($i+1) . ": تعداد مقادیر کریډیټ (" . count($credits_array) . ") با تعداد فرعی کوډها (" . count($sub_codes_array) . ") مطابقت ندارد!";
                    continue;
                }
                
                // Check budget for each sub-code
                foreach ($sub_codes_array as $index => $sub_code) {
                    $debit = isset($debits_array[$index]) ? $debits_array[$index] : 0;
                    $credit = isset($credits_array[$index]) ? $credits_array[$index] : 0;
                    
                    // Check budget only for debit entries
                    if ($debit > 0) {
                        $check_stmt = $conn->prepare("
                            SELECT budget, actual 
                            FROM budget_details 
                            WHERE general_code = ? AND sub_code = ?
                        ");
                        $check_stmt->bind_param("ss", $general_code, $sub_code);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $row = $check_result->fetch_assoc();
                            $remaining_budget = $row['budget'];
                            
                            if ($debit > $remaining_budget) {
                                $budget_errors[] = "ردیف " . ($i+1) . " (فرعی کوډ $sub_code): مبلغ مصرف ($debit) از بودجه باقیمانده ($remaining_budget) بیشتر است!";
                            } else {
                                // Track amount for each (general_code, sub_code) combination
                                $key = $general_code . '_' . $sub_code;
                                if (!isset($code_amounts[$key])) {
                                    $code_amounts[$key] = [
                                        'general_code' => $general_code,
                                        'sub_code' => $sub_code,
                                        'debit' => 0,
                                        'credit' => 0
                                    ];
                                }
                                $code_amounts[$key]['debit'] += $debit;
                                $code_amounts[$key]['credit'] += $credit;
                            }
                        } else {
                            $budget_errors[] = "ردیف " . ($i+1) . ": کوډ $general_code با فرعی کوډ $sub_code در سیستم بودجه موجود نیست!";
                        }
                        $check_stmt->close();
                    }
                }
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
        
        $expense_type_code = $_POST['expense_type'] ?? '';
        $expense_type_desc = $_POST['expense_type'] ?? ''; // Using same value for desc

        $stmt->bind_param(
            "isssssssssssddds",
            $expense_type_code,
            $expense_type_desc,
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
            (voucher_id, details, general_code, sub_code, debit, credit, debit_per_sub_code, credit_per_sub_code)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        
        foreach ($_POST['details'] as $i => $detail) {
            if (trim($detail) === '') continue;

            $debit  = $_POST['debit'][$i] ?? 0;
            $credit = $_POST['credit'][$i] ?? 0;
            $general_code = $_POST['general_code'][$i];
            $sub_code = $_POST['sub_code'][$i];
            $debit_per_sub_code = $_POST['debit_per_sub_code'][$i] ?? '';
            $credit_per_sub_code = $_POST['credit_per_sub_code'][$i] ?? '';

            $stmt->bind_param(
                "isssddss",
                $voucher_id,
                $detail,
                $general_code,
                $sub_code,
                $debit,
                $credit,
                $debit_per_sub_code,
                $credit_per_sub_code
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
            $sub_code = $data['sub_code'];
            $debit = $data['debit'];
            
            if ($debit > 0) {
                $check_stmt = $conn->prepare("
                    SELECT budget, actual 
                    FROM budget_details 
                    WHERE general_code = ? AND sub_code = ? 
                    FOR UPDATE
                ");
                $check_stmt->bind_param("ss", $general_code, $sub_code);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $row = $check_result->fetch_assoc();
                    $current_budget = $row['budget'];
                    $current_actual = $row['actual'];
                    
                    $new_budget = $current_budget - $debit;
                    $new_actual = $current_actual + $debit;
                    $original_budget = $current_budget + $current_actual;
                    
                    if ($original_budget > 0) {
                        $new_percent = min(100, ($new_actual / $original_budget) * 100);
                        $new_percent = round($new_percent, 2);
                    } else {
                        $new_percent = 0;
                    }
                    
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
                        $sub_code
                    );
                    
                    if ($update_stmt->execute()) {
                        $total_updated++;
                        $debug_output .= "Updated: $general_code (Sub: $sub_code) - ";
                        $debug_output .= "Old Budget: $current_budget, New Budget: $new_budget, ";
                        $debug_output .= "Amount Used: $debit<br>";
                    } else {
                        $debug_output .= "Failed to update: $general_code (Sub: $sub_code) - ";
                        $debug_output .= "Error: " . $update_stmt->error . "<br>";
                    }
                    $update_stmt->close();
                } else {
                    $debug_output .= "Not found in budget_details: $general_code (Sub: $sub_code)<br>";
                }
                $check_stmt->close();
            }
        }

        $conn->commit();
        
        $_SESSION['success'] = "✅ سند په بریالیتوب ثبت شو";
        if ($total_updated > 0) {
            $_SESSION['success'] .= "<br>✅ بودیجې تازه شوې ($total_updated کوډونه)";
        }
        
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

/* ================= FETCH DATA FROM DATABASE ================= */
$bab_query = "SELECT DISTINCT sub_code FROM budget_details ORDER BY sub_code ASC";
$babResult = $conn->query($bab_query);

$bab_options = '';
$debug_info = '';

if (!$babResult) {
    $debug_info = "Query error: " . $conn->error;
    $bab_options = '<option value="">خطا در دریافت داده ها</option>';
} elseif ($babResult->num_rows > 0) {
    while($row = $babResult->fetch_assoc()) {
        $bab_value = htmlspecialchars($row['sub_code']);
        $bab_options .= '<option value="' . $bab_value . '">' . $bab_value . '</option>';
    }
} else {
    $bab_options = '<option value="">-- هیچ داده‌ای یافت نشد --</option>';
}

// Fetch all sub-codes for datalist
$sub_code_query = "SELECT DISTINCT sub_code FROM budget_details ORDER BY sub_code ASC";
$sub_code_result = $conn->query($sub_code_query);
$all_sub_codes = [];
if ($sub_code_result) {
    while($row = $sub_code_result->fetch_assoc()) {
        $all_sub_codes[] = $row['sub_code'];
    }
}

// Fetch codes for suggestions
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
$all_general_codes = [];

if ($codeResult) {
    while($row = $codeResult->fetch_assoc()){
        $general_code = $row['general_code'];
        $sub_code = $row['sub_code'];
        
        $composite_key = $general_code . '_' . $sub_code;
        
        $all_codes[$composite_key] = [
            'general_code' => $general_code,
            'sub_code' => $sub_code,
            'remaining_budget' => $row['remaining_budget'],
            'spent' => $row['spent'],
            'original_budget' => $row['original_budget'],
            'current_percent' => $row['current_percent']
        ];
        
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
/* Your existing CSS remains the same */
body{font-family:'Segoe UI', Tahoma, Arial; background:#f2f5f7; padding:20px;}
.page{width:90%; max-width:1200px; margin:auto; padding:20px; border:2px solid #000; border-radius:10px; background:#fff;}
table{width:100%; border-collapse:collapse; font-size:14px; margin-bottom:10px;}
td, th{border:1px solid #000; padding:6px; vertical-align:middle;}
.center{text-align:center;}
.right{text-align:right;}
.left{text-align:left;}
.gray{background:#e6e6e6; font-weight:bold; color:#333;}
input[type=text], input[type=number], input[type=date], select, textarea{
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
.code-suggestion{font-size:11px; color:#666; margin-top:2px; padding:3px; background:#f8f9fa; border-radius:3px;}
.sub-code-info{font-size:10px; color:#555; background:#f0f0f0; padding:2px 5px; border-radius:3px; margin-top:2px;}
.amount-info{font-size:10px; color:#333; background:#e6f7ff; padding:2px 5px; border-radius:3px; margin-top:2px;}
.budget-ok{color:green;}
.budget-warning{color:orange;}
.budget-error{color:red;}
.budget-exhausted{color:red; font-weight:bold;}
.footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 10px;
    border-top: 1px solid #000;
    font-size: 12px;
    color: #666;
}
.footer-left {
    text-align: left;
}
.footer-right {
    text-align: right;
}
.sub-code-hint {
    font-size: 10px;
    color: #666;
    margin-top: 2px;
    font-style: italic;
}
.amount-row {
    background: #f9f9f9;
    border-top: 1px dashed #ddd;
}
.amount-inputs {
    display: flex;
    gap: 5px;
    margin-top: 5px;
}
.amount-inputs input {
    flex: 1;
}
.amount-label {
    font-size: 10px;
    color: #666;
}

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
                 <td>
                    <textarea name="sub_code[]" rows="2" placeholder="هر فرعی کوډ در یک خط (یا با کاما جدا کنید)" oninput="updateSubCodeInfo(this)"></textarea>
                    <div class="sub-code-info"></div>
                    <div class="sub-code-hint">هر فرعی کوډ در یک خط جداگانه وارد کنید یا با کاما جدا کنید</div>
                 </td>
                 <td>
                    <input type="number" step="0.01" name="debit[]" class="debit-input" readonly>
                    <div class="amount-inputs">
                        <div>
                            <div class="amount-label">ډبیټ برای هر فرعی کوډ:</div>
                            <textarea name="debit_per_sub_code[]" rows="2" placeholder="مقادیر ډبیټ (یک مقدار برای هر فرعی کوډ)" oninput="validateSubCodeAmounts(this)"></textarea>
                        </div>
                    </div>
                    <div class="amount-info"></div>
                 </td>
                 <td>
                    <input type="number" step="0.01" name="credit[]" class="credit-input" readonly>
                    <div class="amount-inputs">
                        <div>
                            <div class="amount-label">کریډیټ برای هر فرعی کوډ:</div>
                            <textarea name="credit_per_sub_code[]" rows="2" placeholder="مقادیر کریډیټ (یک مقدار برای هر فرعی کوډ)" oninput="validateSubCodeAmounts(this)"></textarea>
                        </div>
                    </div>
                 </td>`;
    
    // Add event listeners for the new row
    const rowIndex = t.rows.length - 1;
    r.querySelector('.debit-input').addEventListener('input', calculateTotals);
    r.querySelector('.credit-input').addEventListener('input', calculateTotals);
    
    // Add event listener for general code change
    r.querySelector('input[name="general_code[]"]').addEventListener('input', function() {
        showCodeBudgetInfo(this);
        validateSubCodeAmountsForRow(this.closest('tr'));
    });
    
    // Add event listener for sub code change
    r.querySelector('textarea[name="sub_code[]"]').addEventListener('input', function() {
        updateSubCodeInfo(this);
        showCodeBudgetInfo(this.closest('tr').querySelector('input[name="general_code[]"]'));
        validateSubCodeAmountsForRow(this.closest('tr'));
    });
}

function updateSubCodeInfo(textarea) {
    const infoDiv = textarea.nextElementSibling;
    const subCodesString = textarea.value.trim();
    
    if (subCodesString) {
        // Split by new line or comma
        const subCodes = subCodesString.split(/[\n,]+/).map(code => code.trim()).filter(code => code);
        if (subCodes.length > 0) {
            infoDiv.innerHTML = `<span>تعداد فرعی کوډها: ${subCodes.length}</span>`;
            
            // Update amount fields to match number of sub-codes
            const row = textarea.closest('tr');
            const debitPerSubCode = row.querySelector('textarea[name="debit_per_sub_code[]"]');
            const creditPerSubCode = row.querySelector('textarea[name="credit_per_sub_code[]"]');
            
            if (debitPerSubCode) {
                debitPerSubCode.placeholder = `مقادیر ډبیټ (${subCodes.length} مقدار با کاما یا خط جدید جدا کنید)`;
            }
            if (creditPerSubCode) {
                creditPerSubCode.placeholder = `مقادیر کریډیټ (${subCodes.length} مقدار با کاما یا خط جدید جدا کنید)`;
            }
        } else {
            infoDiv.innerHTML = '';
        }
    } else {
        infoDiv.innerHTML = '';
    }
}

function showCodeBudgetInfo(input) {
    const suggestionDiv = input.nextElementSibling;
    const code = input.value.trim();
    const row = input.closest('tr');
    const subCodeTextarea = row.querySelector('textarea[name="sub_code[]"]');
    const subCodesString = subCodeTextarea ? subCodeTextarea.value.trim() : '';
    
    if (code.length > 0) {
        const codeData = <?php echo json_encode($all_codes); ?>;
        const subCodes = subCodesString.split(/[\n,]+/).map(code => code.trim()).filter(code => code);
        
        if (subCodes.length > 0) {
            let html = '';
            let totalRemaining = 0;
            let allValid = true;
            
            subCodes.forEach((subCode, index) => {
                const compositeKey = code + '_' + subCode;
                if (codeData[compositeKey]) {
                    const data = codeData[compositeKey];
                    const remaining = data.remaining_budget;
                    const spent = data.spent;
                    const original = data.original_budget;
                    const currentPercent = data.current_percent || 0;
                    
                    totalRemaining += remaining;
                    
                    html += `<div style="margin-bottom: 5px; padding: 3px; border-bottom: 1px solid #eee;">
                        <strong>فرعی کوډ ${index + 1}: ${subCode}</strong><br>
                        <span>بودجه اصلی: ${original.toLocaleString()}</span><br>
                        <span>مصرف شده: ${spent.toLocaleString()}</span><br>
                        <span class="${remaining > 0 ? 'budget-ok' : 'budget-error'}">بودجه باقیمانده: ${remaining.toLocaleString()}</span><br>
                        <span>درصد مصرف: ${currentPercent}%</span>
                    </div>`;
                } else {
                    allValid = false;
                    html += `<div style="margin-bottom: 5px; padding: 3px; border-bottom: 1px solid #eee;">
                        <strong>فرعی کوډ ${index + 1}: ${subCode}</strong><br>
                        <span class="budget-error">این فرعی کوډ در سیستم بودجه موجود نیست!</span>
                    </div>`;
                }
            });
            
            if (allValid && subCodes.length > 1) {
                html += `<div style="margin-top: 5px; padding: 3px; background: #e6ffe6;">
                    <strong>مجموع بودجه باقیمانده: ${totalRemaining.toLocaleString()}</strong>
                </div>`;
            }
            
            suggestionDiv.innerHTML = html;
        } else {
            suggestionDiv.innerHTML = '<span style="color: #999;">لطفاً فرعی کوډها را وارد کنید</span>';
        }
    } else {
        suggestionDiv.innerHTML = "";
    }
}

function validateSubCodeAmounts(textarea) {
    const row = textarea.closest('tr');
    validateSubCodeAmountsForRow(row);
}

function validateSubCodeAmountsForRow(row) {
    const subCodeTextarea = row.querySelector('textarea[name="sub_code[]"]');
    const debitPerSubCode = row.querySelector('textarea[name="debit_per_sub_code[]"]');
    const creditPerSubCode = row.querySelector('textarea[name="credit_per_sub_code[]"]');
    const generalCodeInput = row.querySelector('input[name="general_code[]"]');
    const amountInfoDiv = row.querySelector('.amount-info');
    const debitInput = row.querySelector('input[name="debit[]"]');
    const creditInput = row.querySelector('input[name="credit[]"]');
    
    const subCodesString = subCodeTextarea ? subCodeTextarea.value.trim() : '';
    const subCodes = subCodesString.split(/[\n,]+/).map(code => code.trim()).filter(code => code);
    
    if (subCodes.length === 0) {
        if (amountInfoDiv) amountInfoDiv.innerHTML = '';
        if (debitInput) debitInput.value = '';
        if (creditInput) creditInput.value = '';
        return;
    }
    
    const code = generalCodeInput ? generalCodeInput.value.trim() : '';
    const codeData = <?php echo json_encode($all_codes); ?>;
    
    // Validate debit amounts
    let debitTotal = 0;
    let debitErrors = [];
    let debitAmounts = [];
    
    if (debitPerSubCode && debitPerSubCode.value.trim()) {
        debitAmounts = debitPerSubCode.value.split(/[\n,]+/).map(val => {
            const trimmed = val.trim();
            return trimmed === '' ? 0 : parseFloat(trimmed) || 0;
        }).filter(val => val !== '');
        
        if (debitAmounts.length !== subCodes.length) {
            debitErrors.push(`تعداد مقادیر ډبیټ (${debitAmounts.length}) با تعداد فرعی کوډها (${subCodes.length}) مطابقت ندارد`);
        } else {
            debitTotal = debitAmounts.reduce((sum, val) => sum + val, 0);
            
            // Check budget for each sub-code
            debitAmounts.forEach((amount, index) => {
                if (amount > 0 && code && subCodes[index]) {
                    const compositeKey = code + '_' + subCodes[index];
                    if (codeData[compositeKey]) {
                        const remaining = codeData[compositeKey].remaining_budget;
                        if (amount > remaining) {
                            debitErrors.push(`فرعی کوډ ${subCodes[index]}: مبلغ ${amount.toLocaleString()} از بودجه باقیمانده (${remaining.toLocaleString()}) بیشتر است`);
                        }
                    } else if (code) {
                        debitErrors.push(`فرعی کوډ ${subCodes[index]}: در سیستم بودجه موجود نیست`);
                    }
                }
            });
        }
    }
    
    // Validate credit amounts
    let creditTotal = 0;
    let creditErrors = [];
    let creditAmounts = [];
    
    if (creditPerSubCode && creditPerSubCode.value.trim()) {
        creditAmounts = creditPerSubCode.value.split(/[\n,]+/).map(val => {
            const trimmed = val.trim();
            return trimmed === '' ? 0 : parseFloat(trimmed) || 0;
        }).filter(val => val !== '');
        
        if (creditAmounts.length !== subCodes.length) {
            creditErrors.push(`تعداد مقادیر کریډیټ (${creditAmounts.length}) با تعداد فرعی کوډها (${subCodes.length}) مطابقت ندارد`);
        } else {
            creditTotal = creditAmounts.reduce((sum, val) => sum + val, 0);
        }
    }
    
    // Update row totals
    if (debitInput) debitInput.value = debitTotal.toFixed(2);
    if (creditInput) creditInput.value = creditTotal.toFixed(2);
    
    // Update amount info display
    if (amountInfoDiv) {
        let infoHtml = '';
        if (debitTotal > 0) {
            infoHtml += `مجموع ډبیټ: ${debitTotal.toLocaleString()}`;
        }
        if (creditTotal > 0) {
            if (infoHtml) infoHtml += '<br>';
            infoHtml += `مجموع کریډیټ: ${creditTotal.toLocaleString()}`;
        }
        
        // Show errors if any
        const allErrors = [...debitErrors, ...creditErrors];
        if (allErrors.length > 0) {
            infoHtml += `<br><span style="color: #dc3545;">خطاها:<br>${allErrors.join('<br>')}</span>`;
        }
        
        amountInfoDiv.innerHTML = infoHtml;
    }
    
    // Update main totals
    calculateTotals();
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
    
    // Check each row
    document.querySelectorAll('input[name="general_code[]"]').forEach((codeInput, index) => {
        const detail = document.querySelectorAll('input[name="details[]"]')[index].value;
        const subCodeTextarea = document.querySelectorAll('textarea[name="sub_code[]"]')[index];
        const subCodesString = subCodeTextarea ? subCodeTextarea.value.trim() : '';
        const debitPerSubCode = document.querySelectorAll('textarea[name="debit_per_sub_code[]"]')[index];
        const creditPerSubCode = document.querySelectorAll('textarea[name="credit_per_sub_code[]"]')[index];
        
        if (detail.trim()) {
            const code = codeInput.value.trim();
            
            if (!code) {
                alert(`ردیف ${index + 1}: کوډ عمومی را وارد کنید!`);
                isValid = false;
                return false;
            }
            
            if (!subCodesString) {
                alert(`ردیف ${index + 1}: فرعی کوډها را وارد کنید!`);
                isValid = false;
                return false;
            }
            
            const subCodes = subCodesString.split(/[\n,]+/).map(code => code.trim()).filter(code => code);
            
            if (subCodes.length === 0) {
                alert(`ردیف ${index + 1}: حداقل یک فرعی کوډ معتبر وارد کنید!`);
                isValid = false;
                return false;
            }
            
            // Check each sub-code
            subCodes.forEach((subCode, subIndex) => {
                const compositeKey = code + '_' + subCode;
                if (!codeData[compositeKey]) {
                    alert(`ردیف ${index + 1} (فرعی کوډ ${subCode}): کوډ "${code}" با فرعی کوډ "${subCode}" در سیستم بودجه موجود نیست!`);
                    isValid = false;
                }
            });
            
            // Check debit amounts if entered
            if (debitPerSubCode && debitPerSubCode.value.trim()) {
                const debitAmounts = debitPerSubCode.value.split(/[\n,]+/).map(val => {
                    const trimmed = val.trim();
                    return trimmed === '' ? 0 : parseFloat(trimmed) || 0;
                }).filter(val => val !== '');
                
                if (debitAmounts.length !== subCodes.length) {
                    alert(`ردیف ${index + 1}: تعداد مقادیر ډبیټ (${debitAmounts.length}) با تعداد فرعی کوډها (${subCodes.length}) مطابقت ندارد!`);
                    isValid = false;
                } else {
                    // Check budget for each debit amount
                    debitAmounts.forEach((amount, amountIndex) => {
                        if (amount > 0) {
                            const subCode = subCodes[amountIndex];
                            const compositeKey = code + '_' + subCode;
                            if (codeData[compositeKey]) {
                                const remaining = codeData[compositeKey].remaining_budget;
                                if (amount > remaining) {
                                    alert(`ردیف ${index + 1} (فرعی کوډ ${subCode}): مبلغ مصرف (${amount.toLocaleString()}) از بودجه باقیمانده (${remaining.toLocaleString()}) بیشتر است!`);
                                    isValid = false;
                                }
                            }
                        }
                    });
                }
            }
            
            // Check credit amounts if entered
            if (creditPerSubCode && creditPerSubCode.value.trim()) {
                const creditAmounts = creditPerSubCode.value.split(/[\n,]+/).map(val => {
                    const trimmed = val.trim();
                    return trimmed === '' ? 0 : parseFloat(trimmed) || 0;
                }).filter(val => val !== '');
                
                if (creditAmounts.length !== subCodes.length) {
                    alert(`ردیف ${index + 1}: تعداد مقادیر کریډیټ (${creditAmounts.length}) با تعداد فرعی کوډها (${subCodes.length}) مطابقت ندارد!`);
                    isValid = false;
                }
            }
            
            // Check if at least one amount is entered
            const debitInput = document.querySelectorAll('input[name="debit[]"]')[index];
            const creditInput = document.querySelectorAll('input[name="credit[]"]')[index];
            
            if ((!debitInput || !debitInput.value || parseFloat(debitInput.value) === 0) && 
                (!creditInput || !creditInput.value || parseFloat(creditInput.value) === 0)) {
                alert(`ردیف ${index + 1}: حداقل یکی از مقادیر ډبیټ یا کریډیټ را وارد کنید!`);
                isValid = false;
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
    
    // Initialize all rows
    document.querySelectorAll('textarea[name="sub_code[]"]').forEach(textarea => {
        updateSubCodeInfo(textarea);
        const row = textarea.closest('tr');
        const generalCodeInput = row.querySelector('input[name="general_code[]"]');
        if (generalCodeInput && generalCodeInput.value) {
            showCodeBudgetInfo(generalCodeInput);
        }
        validateSubCodeAmountsForRow(row);
    });
    
    calculateTotals();
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
مالی او اداری معاونیت<br>مالی او حساسبی ریاست <br>
د محاسبی او معاشاتو آمریت</td>
</tr>
</table>

<!-- EXPENSE TYPE -->
<table>
<tr>
<td class="right" style="width:150px;">نوعیت مصرف:</td>
<td colspan="3">
    <select name="expense_type" required>
        <option value="">-- نوعیت مصرف انتخاب کړئ --</option>
        <?= $bab_options ?>
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
<table id="voucherItemsTable">
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
    <datalist id="codeList">
        <?php foreach($all_general_codes as $code): ?>
            <option value="<?= htmlspecialchars($code) ?>">
        <?php endforeach; ?>
    </datalist>
    <div class="code-suggestion"></div>
</td>
<td>
    <textarea name="sub_code[]" rows="2" placeholder="هر فرعی کوډ در یک خط (یا با کاما جدا کنید)" required></textarea>
    <div class="sub-code-info"></div>
    <div class="sub-code-hint">هر فرعی کوډ در یک خط جداگانه وارد کنید یا با کاما جدا کنید</div>
</td>
<td>
    <input type="number" step="0.01" name="debit[]" class="debit-input" readonly>
    <div class="amount-inputs">
        <div>
            <div class="amount-label">ډبیټ برای هر فرعی کوډ:</div>
            <textarea name="debit_per_sub_code[]" rows="2" placeholder="مقادیر ډبیټ (یک مقدار برای هر فرعی کوډ)"></textarea>
        </div>
    </div>
    <div class="amount-info"></div>
</td>
<td>
    <input type="number" step="0.01" name="credit[]" class="credit-input" readonly>
    <div class="amount-inputs">
        <div>
            <div class="amount-label">کریډیټ برای هر فرعی کوډ:</div>
            <textarea name="credit_per_sub_code[]" rows="2" placeholder="مقادیر کریډیټ (یک مقدار برای هر فرعی کوډ)"></textarea>
        </div>
    </div>
</td>
</tr>
</tbody>
</table>

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

<?php if($error): ?>
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

<?php $conn->close(); ?>
</body>
</html>