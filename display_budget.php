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

// Fetch all data
$sql = "SELECT * FROM budget_details ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>نمایش داده‌های بودجه</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    font-family: Tahoma, Arial;
    margin: 0;
    direction: rtl;
    background: #fff;
    padding: 20px;
}
h3 {
    margin-bottom: 15px;
}
.table-container {
    max-height: 500px; /* Adjust height as needed */
    overflow-y: auto;
    border: 1px solid #000;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
td, th {
    border: 1px solid #000;
    padding: 6px;
    vertical-align: middle;
}
tr.gray td {
    background: #e6e6e6;
    font-weight: bold;
}
.center {
    text-align: center;
}
</style>
</head>
<body>

<h3 class="center gray">نمایش داده‌های بودجه</h3>

<div class="table-container">
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
    <th>تاریخ ایجاد</th>
</tr>

<?php if ($result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
        <tr class="center">
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['code']); ?></td>
            <td><?php echo htmlspecialchars($row['bab']); ?></td>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td><?php echo $row['date']; ?></td>
            <td><?php echo number_format($row['budget'], 2); ?></td>
            <td><?php echo number_format($row['actual'], 2); ?></td>
            <td><?php echo number_format($row['percent'], 2); ?></td>
            <td><?php echo $row['created_at']; ?></td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="9" class="center">هیچ داده‌ای موجود نیست</td></tr>
<?php endif; ?>

</table>
</div>

</body>
</html>

<?php $conn->close(); ?>
