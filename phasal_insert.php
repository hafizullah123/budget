<?php
// Database connection
$host = "localhost";
$db_name = "budget1";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

session_start();

// Convert Persian/Arabic numbers to English
function convertToEnglishNumbers($string) {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    $string = str_replace($persian, $english, $string);
    $string = str_replace($arabic, $english, $string);
    return $string;
}

// Fetch general_code and remaining budget from bab table
$general_codes = [];
$res = $conn->query("SELECT general_code, budget, expense FROM bab ORDER BY general_code ASC");
if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $remaining = $row['budget'] - $row['expense'];
        $general_codes[$row['general_code']] = $remaining;
    }
}

// Handle POST (Insert multiple rows)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $totalCheck = [];

    // Calculate total budget per general_code for submitted rows
    for ($i = 0; $i < count($_POST['general_code']); $i++) {
        $code = convertToEnglishNumbers($_POST['general_code'][$i]);
        $budget = floatval(convertToEnglishNumbers($_POST['budget'][$i]));
        if (!isset($totalCheck[$code])) $totalCheck[$code] = 0;
        $totalCheck[$code] += $budget;
    }

    // Server-side validation: check if any submitted budget exceeds remaining budget
    $error = '';
    foreach ($totalCheck as $code => $sumBudget) {
        $remaining = $general_codes[$code] ?? 0;
        if ($sumBudget > $remaining) {
            $error = "بودجه فرعی برای کد عمومی $code بیشتر از بودجه باقی مانده است ($sumBudget > $remaining)!";
            break;
        }
    }

    // If there is an error, stop insertion completely
    if ($error) {
        $_SESSION['error_message'] = $error;
        header("Location: phasal_insert.php");
        exit(); // IMPORTANT: stops the insert
    }

    // Validation passed → insert records and update bab table
    $stmt = $conn->prepare("
        INSERT INTO budget_details (general_code, sub_code, description, date, budget, actual, percent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssddd", $general_code, $sub_code, $description, $date, $budget, $actual, $percent);

    for ($i = 0; $i < count($_POST['general_code']); $i++) {
        $general_code = convertToEnglishNumbers($_POST['general_code'][$i]);
        $sub_code     = convertToEnglishNumbers($_POST['sub_code'][$i]);
        $description  = $_POST['description'][$i];
        $date         = $_POST['date'][$i];
        $budget       = floatval(convertToEnglishNumbers($_POST['budget'][$i]));
        $actual       = floatval(convertToEnglishNumbers($_POST['actual'][$i]));
        $percent      = floatval(convertToEnglishNumbers($_POST['percent'][$i]));

        // Update bab table
        $res_bab = $conn->query("SELECT budget, expense FROM bab WHERE general_code='$general_code'");
        if ($res_bab->num_rows > 0) {
            $row_bab = $res_bab->fetch_assoc();
            $new_expense = $row_bab['expense'] + $budget;
            $new_percentage = ($row_bab['budget'] > 0) ? ($new_expense / $row_bab['budget']) * 100 : 0;

            $update_stmt = $conn->prepare("
                UPDATE bab 
                SET expense=?, percentage=?
                WHERE general_code=?
            ");
            $update_stmt->bind_param("dds", $new_expense, $new_percentage, $general_code);
            $update_stmt->execute();
            $update_stmt->close();
        }

        $stmt->execute();
    }
    $stmt->close();
    $_SESSION['success_message'] = "رکوردها با موفقیت ثبت و بودجه عمومی بروزرسانی شد!";
    header("Location: phasal_insert.php");
    exit();
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ثبت بودجه</title>
<style>
body {font-family: Tahoma, Arial; background:#f8f9fa; padding:20px;}
h1 {text-align:center; color:#343a40; margin-bottom:20px;}
form {max-width:1200px; margin:0 auto; background:#fff; padding:25px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1);}
.success-message {background:#d4edda; color:#155724; padding:12px; border-radius:6px; margin-bottom:15px; text-align:center; font-weight:bold;}
.error-message {background:#f8d7da; color:#721c24; padding:12px; border-radius:6px; margin-bottom:15px; text-align:center; font-weight:bold;}
table {width:100%; border-collapse:collapse; margin-bottom:15px;}
th, td {border:1px solid #dee2e6; padding:8px; text-align:center;}
th {background:#e9ecef; font-weight:bold;}
input, select {padding:6px 8px; border:1px solid #ced4da; border-radius:4px; font-size:14px;}
.number-input {text-align:left; direction:ltr;}
select[name="general_code[]"] {max-width:120px;}
input[name="sub_code[]"] {max-width:100px;}
input[name="description[]"] {max-width:250px;}
input[name="date[]"] {max-width:130px;}
input[name="budget[]"], input[name="actual[]"] {max-width:120px;}
input[name="percent[]"] {max-width:80px;}
button {padding:8px 16px; border:none; border-radius:5px; cursor:pointer; font-size:14px; margin:5px;}
button.add {background:#007bff; color:#fff;}
button.save {background:#28a745; color:#fff;}
button.view {background:#17a2b8; color:#fff;}
button:hover {opacity:0.9;}
@media(max-width:768px){
    table, th, td {font-size:12px;}
    button {font-size:12px; padding:6px 12px;}
    input[name="description[]"] {max-width:150px;}
}
</style>
<script>
function convertToEnglishNumbers(str){return str.replace(/[۰-۹٠-٩]/g,d=>'۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(d)%10);}
function validateNumericField(el){el.value=convertToEnglishNumbers(el);}

function addRow(){
    const table=document.getElementById("budgetTable");
    const row=table.insertRow(-1);
    const generalCodes = <?php echo json_encode(array_keys($general_codes)); ?>;
    let options = '<option value="">انتخاب کنید</option>';
    for(let i=0;i<generalCodes.length;i++){
        options += `<option value="${generalCodes[i]}">${generalCodes[i]}</option>`;
    }
    row.innerHTML=`<td><select name="general_code[]" required>${options}</select></td>
    <td><input name="sub_code[]" class="number-input" oninput="validateNumericField(this)"></td>
    <td><input name="description[]" required></td>
    <td><input name="date[]" type="date" required></td>
    <td><input name="budget[]" type="text" class="number-input" oninput="validateNumericField(this)" placeholder="0.00"></td>
    <td><input name="actual[]" type="text" class="number-input" oninput="validateNumericField(this)" placeholder="0.00"></td>
    <td><input name="percent[]" type="text" class="number-input" oninput="validateNumericField(this)" placeholder="%"></td>`;
}
</script>
</head>
<body>
<h1>ثبت بودجه</h1>
<?php if($success_message): ?>
<div class="success-message"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if($error_message): ?>
<div class="error-message"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="action" value="insert">
<table id="budgetTable">
<tr>
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
<select name="general_code[]" required>
    <option value="">انتخاب کنید</option>
    <?php foreach($general_codes as $code => $remaining): ?>
        <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option>
    <?php endforeach; ?>
</select>
</td>
<td><input name="sub_code[]" class="number-input" oninput="validateNumericField(this)"></td>
<td><input name="description[]" required></td>
<td><input name="date[]" type="date" required></td>
<td><input name="budget[]" type="text" class="number-input" oninput="validateNumericField(this)" placeholder="0.00"></td>
<td><input name="actual[]" type="text" class="number-input" oninput="validateNumericField(this)" placeholder="0.00"></td>
<td><input name="percent[]" type="text" class="number-input" oninput="validateNumericField(this)" placeholder="%"></td>
</tr>
</table>
<div style="text-align:center;">
<button type="button" class="add" onclick="addRow()">➕ ردیف جدید</button>
<button type="submit" class="save">💾 ثبت</button>
<button type="button" class="view" onclick="window.location='phasal_list.php'">📄 مشاهده بودجه‌ها</button>
</div>
</form>
</body>
</html>
