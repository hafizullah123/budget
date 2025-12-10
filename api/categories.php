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
    case 'GET':    handleGet();    break;
    case 'POST':   handlePost();   break;
    case 'PUT':    handlePut();    break;
    case 'DELETE': handleDelete(); break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
}

function handleGet() {
    global $conn;

    try {
        $query = "SELECT * FROM categories ORDER BY parent_id ASC, name ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'data' => $stmt->fetchAll(),
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در دریافت: ' . $e->getMessage()]);
    }
}

function handlePost() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام الزامی است']);
        return;
    }

    $name = trim($data['name']);
    $color = $data['color'] ?? '#1a73e8';
    $parent_id = $data['parent_id'] ?? null;

    try {
        $query = "INSERT INTO categories (name, color, parent_id) VALUES (:name, :color, :parent_id)";
        $stmt = $conn->prepare($query);

        $stmt->execute([
            ':name' => $name,
            ':color' => $color,
            ':parent_id' => $parent_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'با موفقیت اضافه شد',
            'id' => $conn->lastInsertId()
        ]);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در اضافه کردن: ' . $e->getMessage()]);
    }
}

function handlePut() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id']) || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه و نام الزامی است']);
        return;
    }

    $id = $data['id'];
    $name = trim($data['name']);
    $color = $data['color'] ?? '#1a73e8';
    $parent_id = $data['parent_id'] ?? null;

    try {
        $query = "UPDATE categories 
                  SET name = :name, color = :color, parent_id = :parent_id 
                  WHERE id = :id";
        $stmt = $conn->prepare($query);

        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':color' => $color,
            ':parent_id' => $parent_id
        ]);

        echo json_encode(['success' => true, 'message' => 'با موفقیت به‌روزرسانی شد']);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در به‌روزرسانی: ' . $e->getMessage()]);
    }
}

function handleDelete() {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه الزامی است']);
        return;
    }

    $id = $data['id'];

    try {
        // check if it has subcategories
        $check = $conn->prepare("SELECT COUNT(*) AS count FROM categories WHERE parent_id = :id");
        $check->execute([':id' => $id]);
        if ($check->fetch()['count'] > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ابتدا زیر دسته‌ها را حذف کنید'
            ]);
            return;
        }

        // check budget items usage
        $check2 = $conn->prepare("SELECT COUNT(*) AS count FROM budget_items WHERE category_id = :id");
        $check2->execute([':id' => $id]);
        if ($check2->fetch()['count'] > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'این دسته‌بندی در آیتم‌های بودجه استفاده شده'
            ]);
            return;
        }

        $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'با موفقیت حذف شد']);

    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در حذف: ' . $e->getMessage()]);
    }
}
?>
