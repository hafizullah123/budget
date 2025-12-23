<?php
// Database connection
$host = "localhost";
$db_name = "budget1";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional predefined general codes
$general_codes = ["1001","1002","1003","1004","1005"];

// Handle form submission with PRG pattern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Function to convert Persian/Dari/Arabic numbers to English
    function convertToEnglishNumbers($string) {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        // Convert Persian and Arabic numbers to English
        $string = str_replace($persian, $english, $string);
        $string = str_replace($arabic, $english, $string);
        
        return $string;
    }
    
    $general_codes_input = $_POST['general_code'];
    $sub_codes_input     = $_POST['sub_code'];
    $descriptions        = $_POST['description'];
    $dates               = $_POST['date'];
    $budgets             = $_POST['budget'];
    $actuals             = $_POST['actual'];
    $percents            = $_POST['percent'];

    $stmt = $conn->prepare("
        INSERT INTO budget_details 
        (general_code, sub_code, description, date, budget, actual, percent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssddd",
        $general_code,
        $sub_code,
        $description,
        $date,
        $budget,
        $actual,
        $percent
    );

    // Prepare check for duplicates
    $check_stmt = $conn->prepare("SELECT id FROM budget_details WHERE general_code = ? AND sub_code = ?");
    $check_stmt->bind_param("ss", $general_code, $sub_code);

    $inserted_rows = 0;
    $skipped_rows = 0;
    for ($i = 0; $i < count($general_codes_input); $i++) {
        // Convert all numeric inputs to English numbers
        $general_code = convertToEnglishNumbers($general_codes_input[$i]);
        $sub_code     = convertToEnglishNumbers($sub_codes_input[$i]);
        $description  = $descriptions[$i];
        $date         = $dates[$i];
        $budget       = convertToEnglishNumbers($budgets[$i]) ?: 0;
        $actual       = convertToEnglishNumbers($actuals[$i]) ?: 0;
        $percent      = convertToEnglishNumbers($percents[$i]) ?: 0;

        // Check for duplicates only if sub_code is not empty
        $is_duplicate = false;
        if (!empty($sub_code)) {
            $check_stmt->execute();
            $result_check = $check_stmt->get_result();
            if ($result_check->num_rows > 0) {
                $is_duplicate = true;
            }
        }

        if (!$is_duplicate) {
            if ($stmt->execute()) {
                $inserted_rows++;
            }
        } else {
            $skipped_rows++;
        }
    }

    $stmt->close();
    $check_stmt->close();
    
    // Use PRG pattern - redirect to GET after POST
    session_start();
    $message = "$inserted_rows ردیف با موفقیت ذخیره شد!";
    if ($skipped_rows > 0) {
        $message .= " $skipped_rows ردیف به دلیل تکراری بودن رد شد!";
    }
    $_SESSION['success_message'] = $message;
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
    exit();
}

// Start session for success message
session_start();

// Check for success message in session
$success_message = "";
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}

// Fetch saved data (sorted)
$result = $conn->query("
    SELECT * FROM budget_details 
    ORDER BY general_code ASC, sub_code ASC
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>جدول تفصیلات بودجه</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{font-family:Tahoma, Arial; background:#fff; padding:20px; margin:0;}
.page{width:300mm; margin:auto; padding:12mm; border:2px solid #000; margin-bottom:20px;}
table{width:100%; border-collapse:collapse; font-size:14px; margin-bottom:30px;}
td, th{border:1px solid #000; padding:6px; height:45px;}
.gray{background:#e6e6e6; font-weight:bold;}
.center{text-align:center;}
input{width:100%; height:100%; border:1px solid #000; padding:8px; box-sizing:border-box;}
button{padding:8px 20px; cursor:pointer; margin:0 5px;}
.success-message {
    background-color: #d4edda;
    color: #155724;
    padding: 15px;
    border: 1px solid #c3e6cb;
    border-radius: 5px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}
.warning {
    background-color: #fff3cd;
    color: #856404;
    padding: 10px;
    border: 1px solid #ffeaa7;
    border-radius: 5px;
    margin: 10px 0;
    text-align: center;
}
.number-input {
    font-family: 'Courier New', monospace;
    text-align: left;
    direction: ltr;
}
@media print{button{display:none;}}
</style>

<script>
// Function to convert Persian/Dari/Arabic numbers to English
function convertToEnglishNumbers(str) {
    if (!str) return '';
    
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    let result = str.toString();
    
    // Convert Persian numbers
    for (let i = 0; i < 10; i++) {
        result = result.replace(new RegExp(persianNumbers[i], 'g'), englishNumbers[i]);
    }
    
    // Convert Arabic numbers
    for (let i = 0; i < 10; i++) {
        result = result.replace(new RegExp(arabicNumbers[i], 'g'), englishNumbers[i]);
    }
    
    // Remove any non-numeric characters except decimal point and minus sign
    result = result.replace(/[^\d\.\-]/g, '');
    
    // Ensure only one decimal point
    const parts = result.split('.');
    if (parts.length > 2) {
        result = parts[0] + '.' + parts.slice(1).join('');
    }
    
    return result;
}

// Function to convert English numbers to Persian for display (optional)
function convertToPersianNumbers(str) {
    if (!str) return '';
    
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    let result = str.toString();
    
    // Convert English numbers to Persian
    for (let i = 0; i < 10; i++) {
        result = result.replace(new RegExp(englishNumbers[i], 'g'), persianNumbers[i]);
    }
    
    return result;
}

// Function to handle number input - converts to English on the fly
function handleNumberInput(el) {
    // Get cursor position before conversion
    const cursorPos = el.selectionStart;
    
    // Convert to English numbers
    el.value = convertToEnglishNumbers(el.value);
    
    // Restore cursor position
    el.setSelectionRange(cursorPos, cursorPos);
    
    // For visual feedback, you can show the Persian version in a placeholder or tooltip
    // but store only English in the value
}

// Function to validate numeric fields
function validateNumericField(el) {
    const originalValue = el.value;
    const englishValue = convertToEnglishNumbers(originalValue);
    
    // If the value changed after conversion, update it
    if (originalValue !== englishValue) {
        el.value = englishValue;
    }
    
    // Ensure proper number format for decimal fields
    if (el.name && (el.name.includes('budget') || el.name.includes('actual') || el.name.includes('percent'))) {
        // Remove any non-numeric except decimal point
        el.value = el.value.replace(/[^\d\.]/g, '');
        
        // Ensure only one decimal point
        const parts = el.value.split('.');
        if (parts.length > 2) {
            el.value = parts[0] + '.' + parts.slice(1).join('');
        }
        
        // Limit decimal places for percent to 2
        if (el.name.includes('percent') && el.value.includes('.')) {
            const parts = el.value.split('.');
            if (parts[1].length > 2) {
                el.value = parts[0] + '.' + parts[1].substring(0, 2);
            }
        }
    } else if (el.name && (el.name.includes('general_code') || el.name.includes('sub_code'))) {
        // For code fields, only allow integers
        el.value = el.value.replace(/\D/g, '');
    }
}

function addRow(){
    const table = document.getElementById("budgetTable");
    const row = table.insertRow(-1);
    row.innerHTML = `
        <td>
            <input list="generalCodeList" name="general_code[]" 
                   class="number-input" 
                   oninput="validateNumericField(this)" 
                   onblur="validateNumericField(this)" 
                   required>
        </td>
        <td>
            <input name="sub_code[]" 
                   class="number-input" 
                   oninput="validateNumericField(this)" 
                   onblur="validateNumericField(this)">
        </td>
        <td><input name="description[]" placeholder="توضیحات" required></td>
        <td><input name="date[]" type="date" required></td>
        <td>
            <input name="budget[]" 
                   type="text" 
                   class="number-input" 
                   oninput="validateNumericField(this)" 
                   onblur="validateNumericField(this)" 
                   placeholder="0.00">
        </td>
        <td>
            <input name="actual[]" 
                   type="text" 
                   class="number-input" 
                   oninput="validateNumericField(this)" 
                   onblur="validateNumericField(this)" 
                   placeholder="0.00">
        </td>
        <td>
            <input name="percent[]" 
                   type="text" 
                   class="number-input" 
                   oninput="validateNumericField(this)" 
                   onblur="validateNumericField(this)" 
                   placeholder="%">
        </td>
    `;
}

// Set today's date for new rows
function setTodayDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[name="date[]"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = today;
        }
    });
}

// Clear form after submission
function clearForm() {
    const form = document.getElementById('budgetForm');
    form.reset();
    // Keep only the first row
    const table = document.getElementById("budgetTable");
    while (table.rows.length > 2) {
        table.deleteRow(-1);
    }
    setTodayDate();
}

// Call this when page loads
window.onload = function() {
    setTodayDate();
    
    // Initialize all number inputs to English
    const numberInputs = document.querySelectorAll('.number-input, input[name*="budget"], input[name*="actual"], input[name*="percent"], input[name*="general_code"], input[name*="sub_code"]');
    numberInputs.forEach(input => {
        validateNumericField(input);
    });
    
    // Scroll to data section if there's a success message
    if (document.getElementById('successMessage').style.display !== 'none') {
        setTimeout(function() {
            document.getElementById('dataSection').scrollIntoView({behavior: 'smooth'});
        }, 500);
    }
};

// Prevent form resubmission on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// Show Persian numbers as visual feedback while typing (optional feature)
function showPersianPreview(el) {
    const previewId = el.id + '-preview';
    let previewEl = document.getElementById(previewId);
    
    if (!previewEl) {
        previewEl = document.createElement('div');
        previewEl.id = previewId;
        previewEl.style.cssText = 'position: absolute; background: #fff; border: 1px solid #ccc; padding: 2px 5px; font-size: 12px; z-index: 1000; color: #666;';
        el.parentNode.appendChild(previewEl);
    }
    
    if (el.value) {
        const persianValue = convertToPersianNumbers(el.value);
        previewEl.textContent = 'دری: ' + persianValue;
        previewEl.style.display = 'block';
    } else {
        previewEl.style.display = 'none';
    }
}
</script>
</head>

<body>

<?php if (!empty($success_message)): ?>
<div id="successMessage" class="success-message">
    ✅ <?php echo htmlspecialchars($success_message); ?>
</div>
<?php else: ?>
<div id="successMessage" class="success-message" style="display:none;"></div>
<?php endif; ?>

<div class="page">
<form method="post" id="budgetForm">
<h3 class="center gray">جدول تفصیلات بودجه</h3>

<table id="budgetTable">
<tr class="gray center">
    <th>کوډ عمومی</th>
    <th>کوډ فرعی</th>
    <th>توضیحات</th>
    <th>نېټه</th>
    <th>بودجه</th>
    <th>تحقق</th>
    <th>فیصدی</th>
</tr>

<tr>
    <td>
        <input list="generalCodeList" 
               name="general_code[]" 
               class="number-input" 
               oninput="validateNumericField(this)" 
               onblur="validateNumericField(this)" 
               required>
        <datalist id="generalCodeList">
            <?php foreach($general_codes as $c){ echo "<option value='$c'>"; } ?>
        </datalist>
    </td>
    <td>
        <input name="sub_code[]" 
               class="number-input" 
               oninput="validateNumericField(this)" 
               onblur="validateNumericField(this)">
    </td>
    <td><input name="description[]" required></td>
    <td><input name="date[]" type="date" required></td>
    <td>
        <input name="budget[]" 
               type="text" 
               class="number-input" 
               oninput="validateNumericField(this)" 
               onblur="validateNumericField(this)" 
               placeholder="0.00">
    </td>
    <td>
        <input name="actual[]" 
               type="text" 
               class="number-input" 
               oninput="validateNumericField(this)" 
               onblur="validateNumericField(this)" 
               placeholder="0.00">
    </td>
    <td>
        <input name="percent[]" 
               type="text" 
               class="number-input" 
               oninput="validateNumericField(this)" 
               onblur="validateNumericField(this)" 
               placeholder="%">
    </td>
</tr>
</table>

<div class="center">
    <button type="button" onclick="addRow(); setTodayDate();">➕ ردیف جدید</button>
    <button type="submit">💾 ثبت</button>
    <button type="button" onclick="clearForm()">🧹 پاک کردن فرم</button>
    <button type="button" onclick="window.print()">🖨️ چاپ</button>
</div>

<div class="warning" style="margin-top: 20px; font-size: 12px;">
    توجه: اعداد به صورت خودکار به انگلیسی تبدیل می‌شوند. می‌توانید با اعداد دری (۱۲۳) یا انگلیسی (123) وارد کنید.
</div>
</form>
</div>

<!-- DISPLAY DATA -->
<div class="page" id="dataSection">
<h3 class="center gray">د ثبت شوې بودجه</h3>

<?php if ($result && $result->num_rows > 0): ?>
<table>
<tr class="gray center">
    <th>ID</th>
    <th>کوډ عمومی</th>
    <th>کوډ فرعی</th>
    <th>توضیحات</th>
    <th>نېټه</th>
    <th>بودجه</th>
    <th>تحقق</th>
    <th>فیصدی</th>
    <th>ایجاد</th>
</tr>

<?php 
$row_count = 0;
$total_budget = 0;
$total_actual = 0;
while($row = $result->fetch_assoc()): 
    $row_count++;
    $total_budget += $row['budget'];
    $total_actual += $row['actual'];
?>
<tr class="center">
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['general_code']) ?></td>
    <td><?= htmlspecialchars($row['sub_code']) ?></td>
    <td><?= htmlspecialchars($row['description']) ?></td>
    <td><?= $row['date'] ?></td>
    <td><?= number_format($row['budget'], 2) ?></td>
    <td><?= number_format($row['actual'], 2) ?></td>
    <td><?= number_format($row['percent'], 2) ?></td>
    <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
</tr>
<?php endwhile; ?>
</table>

<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
    <div class="center">
        <strong>تعداد ردیف‌ها:</strong> <?= $row_count ?> | 
        <strong>مجموع بودجه:</strong> <?= number_format($total_budget, 2) ?> | 
        <strong>مجموع تحقق:</strong> <?= number_format($total_actual, 2) ?> | 
        <strong>درصد کل:</strong> <?= $total_budget > 0 ? number_format(($total_actual / $total_budget) * 100, 2) : '0.00' ?>%
    </div>
</div>
<?php else: ?>
<div class="center warning">
    هیچ داده‌ای ثبت نشده است. لطفا اطلاعات جدید را وارد کنید.
</div>
<?php endif; ?>
</div>

<?php $conn->close(); ?>
</body>
</html>