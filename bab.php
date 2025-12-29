<?php
// budget_page.php
$conn = new mysqli("localhost", "root", "", "budget1");
if ($conn->connect_error) die("اتصال به پایگاه داده ناموفق بود: ".$conn->connect_error);

// درج رکورد جدید
if (isset($_POST['action']) && $_POST['action'] === 'insert') {
    $general_code = $_POST['general_code'];
    $description  = $_POST['description'];
    $date         = $_POST['date'];
    $budget       = $_POST['budget'];
    $expense      = isset($_POST['expense']) && $_POST['expense'] !== "" ? $_POST['expense'] : 0;
    $percentage   = ($budget > 0) ? ($expense / $budget) * 100 : 0;

    $stmt = $conn->prepare("INSERT INTO bab (general_code, description, date, budget, expense, percentage) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssddd", $general_code, $description, $date, $budget, $expense, $percentage);
    echo $stmt->execute() ? "success" : "خطا: ".$conn->error;
    $stmt->close();
    exit;
}

// واکشی رکوردها
if (isset($_GET['action']) && $_GET['action'] === 'fetch') {
    $result = $conn->query("SELECT * FROM bab ORDER BY date DESC");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()){
            echo "<tr>
                    <td>".htmlspecialchars($row['general_code'])."</td>
                    <td>".htmlspecialchars($row['description'])."</td>
                    <td>".$row['date']."</td>
                    <td>".number_format($row['budget'],2)."</td>
                    <td>".number_format($row['expense'],2)."</td>
                    <td>".number_format($row['percentage'],2)."%</td>
                    <td><button class='editBtn' data-id='".$row['id']."'>ویرایش</button></td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='7'>هیچ رکوردی موجود نیست</td></tr>";
    }
    exit;
}

// بروزرسانی رکورد
if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $id           = $_POST['id'];
    $general_code = $_POST['general_code'];
    $description  = $_POST['description'];
    $date         = $_POST['date'];
    $budget       = $_POST['budget'];
    $expense      = isset($_POST['expense']) && $_POST['expense'] !== "" ? $_POST['expense'] : 0;
    $percentage   = ($budget > 0) ? ($expense / $budget) * 100 : 0;

    $stmt = $conn->prepare("UPDATE bab SET general_code=?, description=?, date=?, budget=?, expense=?, percentage=? WHERE id=?");
    $stmt->bind_param("sssdddi", $general_code, $description, $date, $budget, $expense, $percentage, $id);
    echo $stmt->execute() ? "success" : "خطا: ".$conn->error;
    $stmt->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>سیستم بودجه</title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
body { font-family: Tahoma, Arial; background: #f0f2f5; margin:0; padding:0; direction: rtl; }
.container { width: 900px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
h2 { text-align: center; margin-bottom: 25px; }
label { display:block; margin-bottom:5px; font-weight:bold; }
input[type=text], input[type=number], input[type=date] { width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:5px; }
button { padding:6px 12px; margin:2px; background:#007BFF; color:white; border:none; border-radius:5px; cursor:pointer; }
button:hover { background:#0056b3; }
table { width:100%; border-collapse: collapse; margin-top:30px; }
th, td { padding:10px; border:1px solid #ccc; text-align:center; }
th { background:#007BFF; color:white; }
tr:nth-child(even) { background:#f2f2f2; }
.message { text-align:center; padding:10px; margin-bottom:15px; border-radius:5px; background:#e0ffe0; color:#2d7a2d; display:none; }
#editModal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); overflow-y:auto; }
.modal-content { background:#fff; width:400px; margin:50px auto; padding:20px; border-radius:8px; position:relative; max-height:80vh; overflow-y:auto; }
.close { position:absolute; right:10px; top:10px; cursor:pointer; font-weight:bold; }
</style>
</head>
<body>
<div class="container">
<h2>ثبت بودجه</h2>
<div class="message" id="message"></div>
<form id="budgetForm">
    <label>کد عمومی</label>
    <input type="text" name="general_code" required>
    <label>توضیحات</label>
    <input type="text" name="description" required>
    <label>تاریخ</label>
    <input type="date" name="date" required>
    <label>مقدار بودجه</label>
    <input type="number" name="budget" step="0.01" required>
    <label>مقدار هزینه (اختیاری)</label>
    <input type="number" name="expense" step="0.01">
    <button type="submit">ثبت</button>
</form>

<h2>تمام رکوردها</h2>
<table>
    <thead>
        <tr>
            <th>کد عمومی</th>
            <th>توضیحات</th>
            <th>تاریخ</th>
            <th>بودجه</th>
            <th>هزینه</th>
            <th>درصد</th>
            <th>عملیات</th>
        </tr>
    </thead>
    <tbody id="budgetTable"></tbody>
</table>
</div>

<!-- Edit Modal -->
<div id="editModal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>ویرایش رکورد بودجه</h3>
        <form id="editForm">
            <input type="hidden" name="id" id="edit_id">
            <label>کد عمومی</label>
            <input type="text" name="general_code" id="edit_general_code" required>
            <label>توضیحات</label>
            <input type="text" name="description" id="edit_description" required>
            <label>تاریخ</label>
            <input type="date" name="date" id="edit_date" required>
            <label>مقدار بودجه</label>
            <input type="number" name="budget" id="edit_budget" step="0.01" required>
            <label>مقدار هزینه (اختیاری)</label>
            <input type="number" name="expense" step="0.01" id="edit_expense">
            <button type="submit">بروزرسانی</button>
        </form>
    </div>
</div>

<script>
function loadTable(){ $("#budgetTable").load("<?php echo $_SERVER['PHP_SELF']; ?>?action=fetch"); }

$(document).ready(function(){
    loadTable();

    $("#budgetForm").submit(function(e){
        e.preventDefault();
        $.post("<?php echo $_SERVER['PHP_SELF']; ?>", $(this).serialize() + "&action=insert", function(response){
            if(response.trim() === "success"){
                $("#message").text("رکورد با موفقیت ثبت شد!").fadeIn().delay(2000).fadeOut();
                $("#budgetForm")[0].reset();
                loadTable();
            } else { $("#message").text(response).fadeIn().delay(4000).fadeOut(); }
        });
    });

    $(document).on("click", ".editBtn", function(){
        let row = $(this).closest("tr");
        $("#edit_id").val($(this).data("id"));
        $("#edit_general_code").val(row.find("td:eq(0)").text());
        $("#edit_description").val(row.find("td:eq(1)").text());
        $("#edit_date").val(row.find("td:eq(2)").text());
        $("#edit_budget").val(row.find("td:eq(3)").text());
        $("#edit_expense").val(row.find("td:eq(4)").text());
        $("#editModal").fadeIn();
    });

    $(".close").click(function(){ $("#editModal").fadeOut(); });

    $("#editForm").submit(function(e){
        e.preventDefault();
        $.post("<?php echo $_SERVER['PHP_SELF']; ?>", $(this).serialize() + "&action=update", function(response){
            if(response.trim() === "success"){
                $("#message").text("رکورد با موفقیت بروزرسانی شد!").fadeIn().delay(2000).fadeOut();
                $("#editModal").fadeOut();
                loadTable();
            } else { $("#message").text(response).fadeIn().delay(4000).fadeOut(); }
        });
    });

    setInterval(loadTable, 5000);
});
</script>
</body>
</html>
