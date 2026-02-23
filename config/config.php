<?php
/* ================= DATABASE CONNECTION ================= */
function getDatabaseConnection() {
    $conn = new mysqli("localhost", "root", "", "budget1");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
?>