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
    color: #111010ff; 
    margin-bottom: 20px;
}

/* Table */
table {
    width: 100%; 
    border-collapse: collapse; 
    background: #fff; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}
th, td {
    padding: 10px; 
    text-align: center; 
    border-bottom: 1px solid #efececff;
}
th {
    background-color: #e0e0e0; 
    color: block; 
    font-weight: bold;
}
tr:hover {
    background-color: #f1f1f1;
}
.number-cell {
    text-align: left; 
    direction: ltr;
}

/* Buttons */
a {
    text-decoration: none; 
    padding: 6px 12px; 
    border-radius: 4px; 
    font-size: 13px;
    display: inline-block;
    margin: 2px;
}
.edit-btn {
    background-color: #FFC107; 
    color: #fff;
}


a:hover {
    opacity: 0.85;
}

/* Responsive */
@media(max-width:768px){
    th, td { font-size: 12px; padding: 8px; }
    a { font-size: 11px; padding: 4px 8px; }
}
</style>
</head>
<body>

<h2>لیست بودجه‌ها</h2>

<table>
<tr>
<th>کد عمومی</th>
<th>کد فرعی</th>
<th>توضیحات</th>
<th>تاریخ</th>
<th>بودجه</th>
<th>تحقق</th>
<th>درصد</th>
<th>عملیات</th>
</tr>

<?php if($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td class="number-cell"><?= htmlspecialchars($row['general_code']) ?></td>
        <td class="number-cell"><?= htmlspecialchars($row['sub_code']) ?></td>
        <td><?= htmlspecialchars($row['description']) ?></td>
        <td><?= htmlspecialchars($row['date']) ?></td>
        <td class="number-cell"><?= number_format($row['budget'], 2) ?></td>
        <td class="number-cell"><?= number_format($row['actual'], 2) ?></td>
        <td class="number-cell"><?= number_format($row['percent'], 2) ?>%</td>
        <td>
            <a class="edit-btn" href="update_phasal.php?id=<?= $row['id'] ?>">ویرایش</a>
            
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="8" style="text-align:center; padding:15px;">هیچ رکوردی وجود ندارد.</td></tr>
<?php endif; ?>

</table>

</body>
</html>
