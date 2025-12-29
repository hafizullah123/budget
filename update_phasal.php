<?php
$host = "localhost";
$db_name = "budget1";
$username = "root";
$password = "";

// Connect to database
$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing record
$row = $id ? $conn->query("SELECT * FROM budget_details WHERE id=$id")->fetch_assoc() : null;

// Handle update (AJAX or normal POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    $general_code = $_POST['general_code'] ?? '';
    $sub_code     = $_POST['sub_code'] ?? '';
    $description  = $_POST['description'] ?? '';
    $date         = $_POST['date'] ?? '';
    $budget       = floatval($_POST['budget'] ?? 0);
    $actual       = floatval($_POST['actual'] ?? 0);
    $percent      = ($budget > 0) ? ($actual / $budget * 100) : 0;

    $stmt = $conn->prepare("UPDATE budget_details 
        SET general_code=?, sub_code=?, description=?, date=?, budget=?, actual=?, percent=? 
        WHERE id=?");
    $stmt->bind_param("sssssddi", $general_code, $sub_code, $description, $date, $budget, $actual, $percent, $id);
    $stmt->execute();
    $stmt->close();

    // Redirect to list page after update
    header("Location:phasal_list.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>ویرایش بودجه</title>
<style>
body { font-family: Tahoma, Arial; padding: 20px; background-color: #f0f0f0; }
h2 { text-align: center; color: #333; }
form { max-width: 500px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
label { display: block; margin: 10px 0 5px; }
input, textarea { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
button { margin-top: 15px; padding: 10px 15px; border: none; border-radius: 4px; background-color: #4CAF50; color: #fff; cursor: pointer; }
button:hover { background-color: #45a049; }
.error { text-align: center; color: red; font-weight: bold; margin-top: 20px; }
</style>
</head>
<body>

<h2>ویرایش بودجه</h2>

<?php if (!$row): ?>
    <div class="error">رکورد مورد نظر یافت نشد.</div>
<?php else: ?>
<form id="budgetForm" method="POST">
    <label>کد عمومی</label>
    <input type="text" name="general_code" value="<?= htmlspecialchars($row['general_code'] ?? '') ?>" required>

    <label>کد فرعی</label>
    <input type="text" name="sub_code" value="<?= htmlspecialchars($row['sub_code'] ?? '') ?>" required>

    <label>توضیحات</label>
    <textarea name="description" rows="3" required><?= htmlspecialchars($row['description'] ?? '') ?></textarea>

    <label>تاریخ</label>
    <input type="date" name="date" value="<?= htmlspecialchars($row['date'] ?? '') ?>" required>

    <label>بودجه</label>
    <input type="number" step="0.01" name="budget" value="<?= htmlspecialchars($row['budget'] ?? 0) ?>" required>

    <label>تحقق</label>
    <input type="number" step="0.01" name="actual" value="<?= htmlspecialchars($row['actual'] ?? 0) ?>" required>

    <button type="submit">به‌روزرسانی</button>
</form>
<?php endif; ?>

</body>
</html>
