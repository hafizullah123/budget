<?php
// فایل: api/test_connection.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// تست اتصال به دیتابیس
try {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        // تست query ساده
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        if ($result['test'] == 1) {
            echo json_encode([
                "success" => true,
                "message" => "اتصال به پایگاه داده موفق بود",
                "database" => "budget_system",
                "server" => "localhost"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "خطا در اجرای query تست"
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "اتصال به پایگاه داده برقرار نشد"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "خطا: " . $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>