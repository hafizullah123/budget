<?php
// api/init.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// تست اتصال
require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در اتصال به پایگاه داده'
    ]);
    exit;
}

// تست وجود جداول
try {
    $tables = ['categories', 'budget_items'];
    $missing_tables = [];
    
    foreach ($tables as $table) {
        // Some MySQL/MariaDB versions don't accept bound parameters for SHOW TABLES
        // Use a quoted literal instead to avoid syntax errors
        $quoted = $conn->quote($table);
        $stmt = $conn->query("SHOW TABLES LIKE $quoted");
        if ($stmt === false || $stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo json_encode([
            'success' => true,
            'message' => 'سیستم آماده است',
            'tables' => $tables,
            'status' => 'ready'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'برخی جداول وجود ندارند',
            'missing_tables' => $missing_tables,
            'status' => 'setup_required'
        ]);
    }
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در بررسی جداول: ' . $e->getMessage()
    ]);
}
?>