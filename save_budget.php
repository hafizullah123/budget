<?php
// Database connection
$host = "localhost";
$db_name = "budget1";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Predefined codes for datalist
$codes = ["1001","1002","1003","1004","1005"]; // Replace with your actual codes
sort($codes); // sort datalist codes ascending

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codes_input = $_POST['code'];
    $babs = $_POST['bab'];
    $descriptions = $_POST['description'];
    $dates = $_POST['date'];
    $budgets = $_POST['budget'];
    $actuals = $_POST['actual'];
    $percents = $_POST['percent'];

    $stmt = $conn->prepare("INSERT INTO budget_details (code, bab, description, date, budget, actual, percent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssddd", $code, $bab, $description, $date, $budget, $actual, $percent);

    for ($i = 0; $i < count($codes_input); $i++) {
        $code = $codes_input[$i];
        $description = $descriptions[$i];
        $bab = !empty($babs[$i]) ? $babs[$i] : NULL; // optional
        $date = $dates[$i];
        $budget = $budgets[$i] ?: 0;
        $actual = $actuals[$i] ?: 0;
        $percent = $percents[$i] ?: 0;

        $stmt->execute();
    }

    $stmt->close();
    echo "<script>alert('اطلاعات با موفقیت ذخیره شد!'); window.location.href='save_budget.php';</script>";
}

// Fetch all saved data for display, sorted by code ascending
$result = $conn->query("SELECT * FROM budget_details ORDER BY sub_code ASC");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>جدول تفصیلات بودجه</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{font-family:Tahoma, Arial; margin:0; direction:rtl; background:#fff; padding:20px;}
.page{width:100%; margin:auto; padding:12px; border:1px solid #000;}
table{width:100%; border-collapse:collapse; font-size:14px; margin-bottom:30px;}
td, th{border:1px solid #000; padding:6px; height:45px; vertical-align:middle;}
tr.gray td{background:#e6e6e6; font-weight:bold; height:50px;}
.center{text-align:center;}
input, select{width:100%; height:100%; padding:4px 6px; box-sizing:border-box; border:1px solid #000; font-family:inherit; font-size:14px;}
button{padding:8px 20px; font-size:14px; cursor:pointer;}
.table-container{max-height:400px; overflow-y:auto;}
@media print{button{display:none;}}
</style>

<script>
function toEnglishNumber(el){ el.value = el.value.replace(/[^\d.]/g,''); }

// Function to add new row dynamically
function addRow() {
    const table = document.querySelector('#budgetTable tbody');
    const newRow = table.insertRow(-1);
    newRow.innerHTML = `
        <td>
            <input list="codeList" name="code[]" inputmode="numeric" pattern="[0-9]+" oninput="toEnglishNumber(this)" required>
        </td>
        <td><input type="text" name="bab[]" placeholder="باب (اختیاری)"></td>
        <td><input type="text" name="description[]" placeholder="توضیحات" required></td>
        <td><input type="date" name="date[]" required></td>
        <td><input type="number" name="budget[]" step="0.01" oninput="toEnglishNumber(this)"></td>
        <td><input type="number" name="actual[]" step="0.01" oninput="toEnglishNumber(this)"></td>
        <td><input type="number" name="percent[]" step="0.01" placeholder="%"></td>
    `;
}
</script>

</head>
<body>
<div class="page">
<form method="post">

<h3 class="center gray">جدول تفصیلات بودجه</h3>

<table id="budgetTable">
<colgroup>
    <col style="width:10%">
    <col style="width:15%">
    <col style="width:25%">
    <col style="width:15%">
    <col style="width:10%">
    <col style="width:10%">
    <col style="width:15%">
</colgroup>
<thead>
<tr class="gray center">
    <td>کوډ</td>
    <td>باب</td>
    <td>توضیحات</td>
    <td>نېټه</td>
    <td>بودجه</td>
    <td>تحقق</td>
    <td>فیصدی</td>
</tr>
</thead>
<tbody>
<tr>
    <td>
        <input list="codeList" name="code[]" inputmode="numeric" pattern="[0-9]+" oninput="toEnglishNumber(this)" required>
        <datalist id="codeList">
            <?php foreach($codes as $c){ echo "<option value='$c'>"; } ?>
        </datalist>
    </td>
    <td><input type="text" name="bab[]" placeholder="باب (اختیاری)"></td>
    <td><input type="text" name="description[]" placeholder="توضیحات" required></td>
    <td><input type="date" name="date[]" required></td>
    <td><input type="number" name="budget[]" step="0.01" oninput="toEnglishNumber(this)"></td>
    <td><input type="number" name="actual[]" step="0.01" oninput="toEnglishNumber(this)"></td>
    <td><input type="number" name="percent[]" step="0.01" placeholder="%"></td>
</tr>
</tbody>
</table>

<div class="center" style="margin-top:15px;">
    <button type="button" onclick="addRow()">➕ ردیف جدید</button>
    <button type="submit">💾 ثبت</button>
    <button type="button" onclick="window.print()">🖨️ چاپ</button>
</div>

</form>
</div>

<!-- Display saved data sorted by code ASC -->
<div class="page table-container">
<h3 class="center gray">د ثبت شده بودجه‌ها (مرتب شده بر اساس کوډ)</h3>

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
    <th>تاریخ ایجاد</th>
</tr>

<?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
        <tr class="center">
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['general_code']); ?></td>
            <td><?php echo htmlspecialchars($row['sub_code']); ?></td>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td><?php echo $row['date']; ?></td>
            <td><?php echo number_format($row['budget'],2); ?></td>
            <td><?php echo number_format($row['actual'],2); ?></td>
            <td><?php echo number_format($row['percent'],2); ?></td>
            <td><?php echo $row['created_at']; ?></td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="9" class="center">هیچ داده‌ای موجود نیست</td></tr>
<?php endif; ?>
</table>
</div>

<?php $conn->close(); ?>
</body>
</html>
