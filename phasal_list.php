<?php
// Database connection
$host = "localhost";
$db_name = "budget1";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch all budget records
$sql = "SELECT * FROM budget_details ORDER BY date DESC, id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>لیست بودجه‌ها</title>
<style>
body{
    font-family: Tahoma, Arial;
    background:#f3f5f9;
    padding:20px;
}

/* Title */
h2{
    text-align:center;
    color:#1f2937;
    margin-bottom:16px;
    font-size:22px;
    font-weight:600;
}

/* Search input */
#searchInput{
    width:100%;
    max-width:420px;
    padding:10px 16px;
    margin-bottom:15px;
    border:1px solid #d1d5db;
    border-radius:30px;
    font-size:14px;
    outline:none;
    transition:0.3s;
}
#searchInput:focus{
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,.15);
}

/* Table container */
.table-wrapper{
    max-height:520px;
    overflow-y:auto;
    background:#fff;
    border-radius:12px;
    box-shadow:0 6px 20px rgba(0,0,0,0.08);
}

/* Table */
table{
    width:100%;
    border-collapse:collapse;
    min-width:850px;
}

/* Header */
th{
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:#fff;
    padding:12px;
    font-size:14px;
    font-weight:600;
    position:sticky;
    top:0;
    z-index:2;
}

/* Cells */
td{
    padding:11px 10px;
    font-size:13px;
    color:#374151;
    border-bottom:1px solid #e5e7eb;
    text-align:center;
}

/* Rows */
tr:nth-child(even){
    background:#f9fafb;
}
tr:hover{
    background:#eef2ff;
}

/* Numbers */
.number-cell{
    direction:ltr;
    text-align:center;
    font-weight:500;
}

/* Columns */
.col-general{ width:70px; }
.col-sub{ width:70px; }
.col-description{
    width:320px;
    text-align:right;
    line-height:1.6;
}

/* Percent colors */
.percent-low{ color:#16a34a; font-weight:700; }
.percent-medium{ color:#d97706; font-weight:700; }
.percent-high{ color:#dc2626; font-weight:700; }

/* Buttons */
a{
    text-decoration:none;
    padding:6px 14px;
    border-radius:20px;
    font-size:12px;
    font-weight:500;
    transition:0.3s;
}

.edit-btn{
    background:#0ea5e9;
    color:#fff;
}
.edit-btn:hover{
    background:#0284c7;
}

/* Responsive */
@media(max-width:768px){
    th,td{
        font-size:12px;
        padding:8px;
    }
    .col-description{
        width:180px;
    }
}

</style>

<script>
// Live search function
function filterTable() {
    let input = document.getElementById("searchInput").value.toLowerCase();
    let table = document.getElementById("budgetTable");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) { // skip header
        let tds = tr[i].getElementsByTagName("td");
        let match = false;
        for (let j = 0; j < tds.length; j++) {
            let txt = tds[j].textContent.toLowerCase();
            if (txt.indexOf(input) > -1) {
                match = true;
                break;
            }
        }
        tr[i].style.display = match ? "" : "none";
    }
}
</script>

</head>
<body>

<h2>لیست بودجه‌ها</h2>

<input type="text" id="searchInput" onkeyup="filterTable()" placeholder="جستجو بر اساس کد عمومی، کد فرعی یا توضیحات...">

<div class="table-wrapper">
<table id="budgetTable">
<tr>
<th class="col-general">کد عمومی</th>
<th class="col-sub">کد فرعی</th>
<th class="col-description">توضیحات</th>
<th>تاریخ</th>
<th>بودجه</th>
<th>تحقق</th>
<th>درصد</th>
<th>عملیات</th>
</tr>

<?php if($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): 
        $percent = floatval($row['percent']);
        if($percent < 50){
            $class = 'percent-low';
        } elseif($percent < 80){
            $class = 'percent-medium';
        } else {
            $class = 'percent-high';
        }
    ?>
    <tr>
        <td class="number-cell col-general"><?= htmlspecialchars($row['general_code']) ?></td>
        <td class="number-cell col-sub"><?= htmlspecialchars($row['sub_code']) ?></td>
        <td class="col-description"><?= htmlspecialchars($row['description']) ?></td>
        <td><?= htmlspecialchars($row['date']) ?></td>
        <td class="number-cell"><?= number_format($row['budget'], 2) ?></td>
        <td class="number-cell"><?= number_format($row['actual'], 2) ?></td>
        <td class="number-cell <?= $class ?>"><?= number_format($row['percent'], 2) ?>%</td>
        <td>
            <a class="edit-btn" href="update_phasal.php?id=<?= $row['id'] ?>">ویرایش</a>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="8" style="text-align:center; padding:15px;">هیچ رکوردی وجود ندارد.</td></tr>
<?php endif; ?>

</table>
</div>

</body>
</html>
