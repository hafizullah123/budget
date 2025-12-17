<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "budget_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch categories for description dropdown
$categories = [];
$sql = "SELECT id, name FROM categories ORDER BY name ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()){
        $categories[] = $row;
    }
}

// Fetch distinct codes from budget_items
$codes = [];
$sql2 = "SELECT DISTINCT code FROM budget_items ORDER BY code ASC";
$result2 = $conn->query($sql2);
if ($result2->num_rows > 0) {
    while($row = $result2->fetch_assoc()){
        $codes[] = $row['code'];
    }
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $codes_post = $_POST['code'];
    $descriptions_post = $_POST['description'];
    $budget_1403_post = $_POST['budget_1403'];
    $actual_1403_post = $_POST['actual_1403'];
    $percent_1403_post = $_POST['percent_1403'];
    $budget_1404_post = $_POST['budget_1404'];
    $actual_1404_post = $_POST['actual_1404'];
    $percent_1404_post = $_POST['percent_1404'];

    for($i=0; $i<count($codes_post); $i++){
        $code = $conn->real_escape_string($codes_post[$i]);
        $description = $conn->real_escape_string($descriptions_post[$i]);
        $budget_1403 = $budget_1403_post[$i] ?: 0;
        $actual_1403 = $actual_1403_post[$i] ?: 0;
        $percent_1403 = $percent_1403_post[$i] ?: 0;
        $budget_1404 = $budget_1404_post[$i] ?: 0;
        $actual_1404 = $actual_1404_post[$i] ?: 0;
        $percent_1404 = $percent_1404_post[$i] ?: 0;

        $sqlInsert = "INSERT INTO budget_items 
            (code, description, budget_1403, actual_1403, percent_1403, budget_1404, actual_1404, percent_1404, created_at)
            VALUES
            ('$code','$description','$budget_1403','$actual_1403','$percent_1403','$budget_1404','$actual_1404','$percent_1404', NOW())";
        $conn->query($sqlInsert);
    }

    echo "<script>alert('Data saved successfully');window.location='".$_SERVER['PHP_SELF']."';</script>";
}
?>
