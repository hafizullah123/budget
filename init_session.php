<?php
/* ================= SESSION MANAGEMENT ================= */
session_start();

// Clear session messages on page load (GET requests only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_POST)) {
    unset($_SESSION['success']);
    unset($_SESSION['error']);
    unset($_SESSION['debug_info']);
}

// Retrieve and clear session messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
$debug_info = $_SESSION['debug_info'] ?? '';

unset($_SESSION['success'], $_SESSION['error'], $_SESSION['debug_info']);
?>