<?php
// api/categories.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
}

function handleGet() {
    global $conn;
    
    try {
        $query = "SELECT * FROM categories ORDER BY name";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $categories = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $categories,
            'count' => count($categories)
        ]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در دریافت دسته‌بندی‌ها: ' . $e->getMessage()]);
    }
}

function handlePost() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام دسته‌بندی الزامی است']);
        return;
    }
    
    $name = trim($data['name']);
    $color = isset($data['color']) ? $data['color'] : '#1a73e8';
    
    try {
        // بررسی تکراری نبودن
        $checkQuery = "SELECT id FROM categories WHERE name = :name";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([':name' => $name]);
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'دسته‌بندی با این نام از قبل وجود دارد']);
            return;
        }
        
        $query = "INSERT INTO categories (name, color) VALUES (:name, :color)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':name' => $name,
            ':color' => $color
        ]);
        
        $lastId = $conn->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'دسته‌بندی با موفقیت اضافه شد',
            'id' => $lastId
        ]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در اضافه کردن دسته‌بندی: ' . $e->getMessage()]);
    }
}

function handlePut() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['id']) || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه و نام دسته‌بندی الزامی است']);
        return;
    }
    
    $id = $data['id'];
    $name = trim($data['name']);
    $color = isset($data['color']) ? $data['color'] : '#1a73e8';
    
    try {
        $query = "UPDATE categories SET name = :name, color = :color WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':color' => $color
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'دسته‌بندی با موفقیت به‌روزرسانی شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'دسته‌بندی یافت نشد']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در به‌روزرسانی دسته‌بندی: ' . $e->getMessage()]);
    }
}

function handleDelete() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه دسته‌بندی الزامی است']);
        return;
    }
    
    $id = $data['id'];
    
    try {
        // بررسی استفاده از دسته‌بندی
        $checkQuery = "SELECT COUNT(*) as count FROM budget_items WHERE category_id = :id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([':id' => $id]);
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'این دسته‌بندی در آیتم‌های بودجه استفاده شده و قابل حذف نیست'
            ]);
            return;
        }
        
        $query = "DELETE FROM categories WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'دسته‌بندی با موفقیت حذف شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'دسته‌بندی یافت نشد']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در حذف دسته‌بندی: ' . $e->getMessage()]);
    }
}
?>