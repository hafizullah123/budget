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

// Optional predefined codes
$codes = ["1001","1002","1003","1004","1005"];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codes_input  = $_POST['code'];
    $babs         = $_POST['bab'];
    $descriptions = $_POST['description'];
    $dates        = $_POST['date'];
    $budgets      = $_POST['budget'];
    $actuals      = $_POST['actual'];
    $percents     = $_POST['percent'];

    $stmt = $conn->prepare("
        INSERT INTO budget_details 
        (code, bab, description, date, budget, actual, percent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssddd",
        $code,
        $bab,
        $description,
        $date,
        $budget,
        $actual,
        $percent
    );

    for ($i = 0; $i < count($codes_input); $i++) {
        $code        = $codes_input[$i];
        $bab         = $babs[$i];
        $description = $descriptions[$i];
        $date        = $dates[$i];
        $budget      = $budgets[$i] ?: 0;
        $actual      = $actuals[$i] ?: 0;
        $percent     = $percents[$i] ?: 0;

        $stmt->execute();
    }

    $stmt->close();
    echo "<script>alert('اطلاعات با موفقیت ذخیره شد!'); window.location.href='save_budget.php';</script>";
}

// Fetch saved data (sorted)
$result = $conn->query("
    SELECT * FROM budget_details 
    ORDER BY bab ASC, description DESC
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>جدول تفصیلات بودجه</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{font-family:Tahoma, Arial; background:#fff; padding:20px;}
.page{width:300mm; margin:auto; padding:12mm; border:2px solid #000;}
table{width:100%; border-collapse:collapse; font-size:14px; margin-bottom:30px;}
td, th{border:1px solid #000; padding:6px; height:45px;}
.gray{background:#e6e6e6; font-weight:bold;}
.center{text-align:center;}
input{width:100%; height:100%; border:1px solid #000;}
button{padding:8px 20px; cursor:pointer;}
@media print{button{display:none;}}
</style>

<script>
function toEnglishNumber(el){
    el.value = el.value.replace(/[^\d.]/g,'');
}

function addRow(){
    const table = document.getElementById("budgetTable");
    const row = table.insertRow(-1);
    row.innerHTML = `
        <td><input list="codeList" name="code[]" required></td>
        <td><input name="bab[]" placeholder="باب" required></td>
        <td><input name="description[]" placeholder="توضیحات" required></td>
        <td><input name="date[]" placeholder="YYYY-MM-DD" required></td>
        <td><input name="budget[]" oninput="toEnglishNumber(this)"></td>
        <td><input name="actual[]" oninput="toEnglishNumber(this)"></td>
        <td><input name="percent[]" oninput="toEnglishNumber(this)"></td>
    `;
}
</script>
</head>

<body>

<div class="page">
<form method="post">
<h3 class="center gray">جدول تفصیلات بودجه</h3>

<table id="budgetTable">
<tr class="gray center">
    <th>کوډ</th>
    <th>باب</th>
    <th>توضیحات</th>
    <th>نېټه</th>
    <th>بودجه</th>
    <th>تحقق</th>
    <th>فیصدی</th>
</tr>

<tr>
    <td>
        <input list="codeList" name="code[]" required>
        <datalist id="codeList">
            <?php foreach($codes as $c){ echo "<option value='$c'>"; } ?>
        </datalist>
    </td>
    <td><input name="bab[]" required></td>
    <td><input name="description[]" required></td>
    <td><input name="date[]" placeholder="YYYY-MM-DD" required></td>
    <td><input name="budget[]" oninput="toEnglishNumber(this)"></td>
    <td><input name="actual[]" oninput="toEnglishNumber(this)"></td>
    <td><input name="percent[]" oninput="toEnglishNumber(this)"></td>
</tr>
</table>

<div class="center">
    <button type="button" onclick="addRow()">➕ ردیف جدید</button>
    <button type="submit">💾 ثبت</button>
    <button type="button" onclick="window.print()">🖨️ چاپ</button>
</div>
</form>
</div>

<!-- DISPLAY DATA -->
<div class="page">
<h3 class="center gray">د ثبت شوې بودجه</h3>

<table>
<tr class="gray center">
    <th>ID</th>
    <th>کوډ</th>
    <th>باب</th>
    <th>توضیحات</th>
    <th>نېټه</th>
    <th>بودجه</th>
    <th>تحقق</th>
    <th>فیصدی</th>
    <th>ایجاد</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr class="center">
    <td><?= $row['id'] ?></td>
    <td><?= $row['code'] ?></td>
    <td><?= $row['bab'] ?></td>
    <td><?= $row['description'] ?></td>
    <td><?= $row['date'] ?></td>
    <td><?= $row['budget'] ?></td>
    <td><?= $row['actual'] ?></td>
    <td><?= $row['percent'] ?></td>
    <td><?= $row['created_at'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<?php $conn->close(); ?>
</body>
</html>
