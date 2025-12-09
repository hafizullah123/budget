<?php
// api/budget.php

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
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    try {
        // دریافت آیتم‌ها
        $query = "SELECT 
                    bi.*,
                    c.name as category_name,
                    c.color as category_color
                  FROM budget_items bi
                  LEFT JOIN categories c ON bi.category_id = c.id
                  ORDER BY bi.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $items = $stmt->fetchAll();
        
        // دریافت آمار
        $statsQuery = "SELECT 
                        COUNT(*) as total_items,
                        SUM(amount) as total_amount,
                        AVG(amount) as avg_amount
                      FROM budget_items";
        
        $statsStmt = $conn->prepare($statsQuery);
        $statsStmt->execute();
        $stats = $statsStmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => $items,
            'stats' => $stats,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => count($items) == $limit
            ]
        ]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در دریافت آیتم‌ها: ' . $e->getMessage()]);
    }
}

function handlePost() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    // اعتبارسنجی
    $errors = [];
    
    // `purpose` is treated as budget code — must be an integer
    if (!isset($data['purpose']) || filter_var($data['purpose'], FILTER_VALIDATE_INT) === false) {
        $errors[] = 'کد بودجه باید عدد صحیح باشد';
    }
    
    if (!isset($data['category_id']) || !is_numeric($data['category_id'])) {
        $errors[] = 'دسته‌بندی الزامی است';
    }
    
    if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
        $errors[] = 'مقدار باید عددی مثبت باشد';
    }

    if (!isset($data['description']) || trim($data['description']) === '') {
        $errors[] = 'توضیحات الزامی است';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'خطا در اعتبارسنجی', 'errors' => $errors]);
        return;
    }
    
    $purpose = (int)$data['purpose'];
    $category_id = (int)$data['category_id'];
    $amount = (float)$data['amount'];
    $description = trim($data['description']);
    
    try {
        // بررسی وجود دسته‌بندی
        $checkCategory = $conn->prepare("SELECT id FROM categories WHERE id = :id");
        $checkCategory->execute([':id' => $category_id]);
        
        if ($checkCategory->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'دسته‌بندی انتخاب شده وجود ندارد']);
            return;
        }
        
        // درج آیتم
        $query = "INSERT INTO budget_items (purpose, category_id, amount, description) 
                  VALUES (:purpose, :category_id, :amount, :description)";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':purpose' => $purpose,
            ':category_id' => $category_id,
            ':amount' => $amount,
            ':description' => $description
        ]);
        
        $lastId = $conn->lastInsertId();
        
        // دریافت آیتم اضافه شده
        $getQuery = "SELECT 
                        bi.*,
                        c.name as category_name,
                        c.color as category_color
                     FROM budget_items bi
                     LEFT JOIN categories c ON bi.category_id = c.id
                     WHERE bi.id = :id";
        
        $getStmt = $conn->prepare($getQuery);
        $getStmt->execute([':id' => $lastId]);
        $newItem = $getStmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'آیتم بودجه با موفقیت اضافه شد',
            'data' => $newItem
        ]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در اضافه کردن آیتم: ' . $e->getMessage()]);
    }
}

function handlePut() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه آیتم الزامی است']);
        return;
    }
    
    $id = (int)$data['id'];
    $updates = [];
    $params = [':id' => $id];
    
    if (isset($data['purpose'])) {
        // validate purpose if provided
        if (filter_var($data['purpose'], FILTER_VALIDATE_INT) === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'کد بودجه باید عدد صحیح باشد']);
            return;
        }
        $updates[] = 'purpose = :purpose';
        $params[':purpose'] = (int)$data['purpose'];
    }
    
    if (isset($data['category_id'])) {
        $updates[] = 'category_id = :category_id';
        $params[':category_id'] = (int)$data['category_id'];
    }
    
    if (isset($data['amount'])) {
        $updates[] = 'amount = :amount';
        $params[':amount'] = (float)$data['amount'];
    }
    
    if (isset($data['description'])) {
        $updates[] = 'description = :description';
        $params[':description'] = trim($data['description']);
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'هیچ داده‌ای برای به‌روزرسانی ارسال نشده']);
        return;
    }
    
    try {
        $query = "UPDATE budget_items SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'آیتم با موفقیت به‌روزرسانی شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'آیتم یافت نشد']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در به‌روزرسانی آیتم: ' . $e->getMessage()]);
    }
}

function handleDelete() {
    global $conn;
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه آیتم الزامی است']);
        return;
    }
    
    $id = (int)$data['id'];
    
    try {
        $query = "DELETE FROM budget_items WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'آیتم با موفقیت حذف شد']);
        } else {
            echo json_encode(['success' => false, 'message' => 'آیتم یافت نشد']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در حذف آیتم: ' . $e->getMessage()]);
    }
}
?>