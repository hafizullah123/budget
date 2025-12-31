<?php
// ================= DATABASE =================
$conn = new mysqli("localhost", "root", "", "budget1");
if ($conn->connect_error) die("Connection failed");

session_start();

// ================= NUMBER CONVERTER =================
function convertToEnglishNumbers($string) {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($arabic, $english, str_replace($persian, $english, $string));
}

// ================= FETCH GENERAL CODES (REMAINING = budget) =================
$general_codes = [];
$res = $conn->query("SELECT general_code, budget FROM bab ORDER BY general_code");
while ($row = $res->fetch_assoc()) {
    $general_codes[$row['general_code']] = $row['budget']; // ✅ remaining
}

// ================= HANDLE POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Check totals per general code
    $totalCheck = [];
    foreach ($_POST['general_code'] as $i => $code) {
        $code   = convertToEnglishNumbers($code);
        $amount = floatval(convertToEnglishNumbers($_POST['budget'][$i]));
        $totalCheck[$code] = ($totalCheck[$code] ?? 0) + $amount;
    }

    foreach ($totalCheck as $code => $sum) {
        if ($sum > ($general_codes[$code] ?? 0)) {
            $_SESSION['error_message'] =
                "مبلغ داده شده بیشتر از بودجه موجود برای کد $code است!";
            header("Location: phasal_insert.php");
            exit;
        }
    }

    // ---- Prepare insert
    $stmt = $conn->prepare("
        INSERT INTO budget_details
        (general_code, sub_code, description, date, budget, actual, percent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // ---- Insert rows
    foreach ($_POST['general_code'] as $i => $code) {

        $general_code = convertToEnglishNumbers($code);
        $sub_code     = convertToEnglishNumbers($_POST['sub_code'][$i]);
        $description  = $_POST['description'][$i];
        $date         = $_POST['date'][$i];
        $amount       = floatval(convertToEnglishNumbers($_POST['budget'][$i]));
        $actual       = floatval(convertToEnglishNumbers($_POST['actual'][$i]));
        $percent      = min(100, max(0, floatval($_POST['percent'][$i])));

        // ---- Fetch bab
        $bab = $conn->query("
            SELECT budget, expense FROM bab WHERE general_code='$general_code'
        ")->fetch_assoc();

        // ---- DECREASE budget & INCREASE expense ✅
        $new_budget  = $bab['budget'] - $amount;
        $new_expense = $bab['expense'] + $amount;

        if ($new_budget < 0) $new_budget = 0;

        $new_percentage = ($bab['budget'] > 0)
            ? ($new_expense / ($bab['budget'] + $bab['expense'])) * 100
            : 0;

        if ($new_percentage > 100) $new_percentage = 100;

        // ---- Update bab
        $upd = $conn->prepare("
            UPDATE bab
            SET budget=?, expense=?, percentage=?
            WHERE general_code=?
        ");
        $upd->bind_param("ddds",
            $new_budget, $new_expense, $new_percentage, $general_code
        );
        $upd->execute();
        $upd->close();

        // ---- Insert details
        $stmt->bind_param(
            "ssssddd",
            $general_code, $sub_code, $description,
            $date, $amount, $actual, $percent
        );
        $stmt->execute();
    }

    $stmt->close();
    $_SESSION['success_message'] = "مبلغ با موفقیت از بودجه عمومی کسر شد";
    header("Location: phasal_insert.php");
    exit;
}

$success_message = $_SESSION['success_message'] ?? '';
$error_message   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>پرداخت از بودجه عمومی</title>
<style>
body{font-family:tahoma;background:#f8f9fa;padding:20px}
form{max-width:1200px;margin:auto;background:#fff;padding:25px;border-radius:10px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #ccc;padding:8px;text-align:center}
th{background:#e9ecef}
.success-message{background:#d4edda;padding:12px;margin-bottom:15px}
.error-message{background:#f8d7da;padding:12px;margin-bottom:15px}
button{padding:8px 16px;border:none;border-radius:5px;cursor:pointer}
.add{background:#007bff;color:#fff}
.save{background:#28a745;color:#fff}
.view{background:#17a2b8;color:#fff}
</style>

<script>
function addRow(){
    const t=document.getElementById("budgetTable");
    const r=t.insertRow(-1);
    const codes=<?=json_encode(array_keys($general_codes))?>;
    let opt='<option></option>';
    codes.forEach(c=>opt+=`<option>${c}</option>`);
    r.innerHTML=`
    <td><select name="general_code[]" required>${opt}</select></td>
    <td><input name="sub_code[]"></td>
    <td><input name="description[]" required></td>
    <td><input type="date" name="date[]" required></td>
    <td><input type="number" step="0.01" name="budget[]" required></td>
    <td><input type="number" step="0.01" name="actual[]"></td>
    <td><input type="number" min="0" max="100" step="0.01" name="percent[]"></td>`;
}
</script>
</head>

<body>

<?php if($success_message): ?><div class="success-message"><?=$success_message?></div><?php endif;?>
<?php if($error_message): ?><div class="error-message"><?=$error_message?></div><?php endif;?>

<form method="post">
<table id="budgetTable">
<tr>
<th>کد عمومی</th><th>کد فرعی</th><th>توضیح</th>
<th>تاریخ</th><th>مبلغ پرداختی</th><th>تحقق</th><th>فیصدی</th>
</tr>
<tr>
<td>
<select name="general_code[]" required>
<option></option>
<?php foreach($general_codes as $c=>$r): ?><option><?=$c?></option><?php endforeach;?>
</select>
</td>
<td><input name="sub_code[]"></td>
<td><input name="description[]" required></td>
<td><input type="date" name="date[]" required></td>
<td><input type="number" step="0.01" name="budget[]" required></td>
<td><input type="number" step="0.01" name="actual[]"></td>
<td><input type="number" min="0" max="100" step="0.01" name="percent[]"></td>
</tr>
</table>

<div style="text-align:center;margin-top:15px">
<button type="button" class="add" onclick="addRow()">➕ ردیف جدید</button>
<button class="save">💾 ثبت پرداخت</button>
<button type="button" class="view" onclick="location.href='phasal_list.php'">📄 لیست</button>
</div>
</form>

</body>
</html>
