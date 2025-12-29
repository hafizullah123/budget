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
body {
    font-family: Tahoma, Arial; 
    padding: 20px; 
    background-color: #f0f2f5;
}

h2 {
    text-align: center; 
    color: #333; 
    margin-bottom: 20px;
}

/* Search input */
#searchInput {
    width: 100%;
    max-width: 400px;
    padding: 8px 12px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}

/* Scrollable table wrapper */
.table-wrapper {
    max-height: 500px;
    overflow-y: auto;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: #fff;
}

/* Table styles */
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th, td {
    padding: 12px 10px;
    text-align: center;
    border-bottom: 1px solid #e0e0e0;
}

th {
    background-color: #007BFF;
    color: #fff;
    position: sticky;
    top: 0;
    z-index: 2;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

tr:hover {
    background-color: #e8f0fe;
}

.number-cell {
    text-align: left; 
    direction: ltr;
}

/* Column widths */
.col-general { width: 60px; }
.col-sub { width: 60px; }
.col-description { width: 300px; text-align: right; }

/* Percentage colors */
.percent-low { color: #28a745; font-weight: bold; }
.percent-medium { color: #ffc107; font-weight: bold; }
.percent-high { color: #dc3545; font-weight: bold; }

/* Buttons */
a {
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    display: inline-block;
    margin: 2px;
    transition: 0.3s;
}

.edit-btn {
    background-color: #17a2b8;
    color: #fff;
}

.edit-btn:hover {
    background-color: #138496;
}

.delete-btn {
    background-color: #dc3545;
    color: #fff;
}

.delete-btn:hover {
    background-color: #b52a37;
    color: #fff;
}

/* Responsive */
@media(max-width:768px){
    th, td { font-size: 12px; padding: 8px; }
    a { font-size: 11px; padding: 4px 8px; }
    .col-description { width: 150px; }
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
