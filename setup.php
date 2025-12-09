<?php
// setup.php
session_start();
header('Content-Type: text/html; charset=utf-8');

// تنظیمات پیش‌فرض
$default_db_host = 'localhost';
$default_db_user = 'root';
$default_db_pass = '';
$default_db_name = 'budget_system';

// اگر فرم ارسال شده
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? $default_db_host;
    $db_user = $_POST['db_user'] ?? $default_db_user;
    $db_pass = $_POST['db_pass'] ?? $default_db_pass;
    $db_name = $_POST['db_name'] ?? $default_db_name;
    
    try {
        // تست اتصال به MySQL
        $conn = new mysqli($db_host, $db_user, $db_pass);
        
        if ($conn->connect_error) {
            $error = "خطا در اتصال به MySQL: " . $conn->connect_error;
        } else {
            // ایجاد دیتابیس
            $sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if ($conn->query($sql) === TRUE) {
                // انتخاب دیتابیس
                $conn->select_db($db_name);
                
                // ایجاد جدول categories
                $sql_categories = "CREATE TABLE IF NOT EXISTS `categories` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `color` VARCHAR(7) DEFAULT '#1a73e8',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                // ایجاد جدول budget_items
                $sql_budget = "CREATE TABLE IF NOT EXISTS `budget_items` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `purpose` VARCHAR(255) NOT NULL,
                    `category_id` INT(11) NOT NULL,
                    `amount` DECIMAL(15,2) NOT NULL,
                    `description` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                if ($conn->query($sql_categories) === TRUE && $conn->query($sql_budget) === TRUE) {
                    // درج دسته‌بندی‌های پیش‌فرض
                    $default_categories = [
                        ['عملیات', '#1a73e8'],
                        ['بازاریابی', '#4CAF50'],
                        ['توسعه', '#FF9800'],
                        ['منابع بشری', '#9C27B0'],
                        ['زیربنا', '#795548'],
                        ['سایر', '#607D8B']
                    ];
                    
                    foreach ($default_categories as $category) {
                        $name = $category[0];
                        $color = $category[1];
                        $sql_insert = "INSERT IGNORE INTO `categories` (`name`, `color`) VALUES ('$name', '$color')";
                        $conn->query($sql_insert);
                    }
                    
                    // ایجاد فایل database.php
                    $config_content = "<?php
// config/database.php
class Database {
    private \$host = '$db_host';
    private \$db_name = '$db_name';
    private \$username = '$db_user';
    private \$password = '$db_pass';
    private \$conn;
    
    public function getConnection() {
        \$this->conn = null;
        
        try {
            \$this->conn = new PDO(
                'mysql:host=' . \$this->host . ';dbname=' . \$this->db_name . ';charset=utf8mb4',
                \$this->username,
                \$this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
                )
            );
        } catch(PDOException \$e) {
            error_log('Connection Error: ' . \$e->getMessage());
            return null;
        }
        
        return \$this->conn;
    }
    
    public function testConnection() {
        try {
            \$conn = \$this->getConnection();
            if (\$conn) {
                // تست query ساده
                \$stmt = \$conn->query('SELECT 1');
                return ['success' => true, 'message' => 'اتصال موفق'];
            }
        } catch(PDOException \$e) {
            return ['success' => false, 'message' => \$e->getMessage()];
        }
        return ['success' => false, 'message' => 'خطای ناشناخته'];
    }
}
?>";
                    
                    // ایجاد پوشه config اگر وجود ندارد
                    if (!is_dir('config')) {
                        mkdir('config', 0755, true);
                    }
                    
                    if (file_put_contents('config/database.php', $config_content)) {
                        $success = "✅ سیستم با موفقیت نصب شد!";
                        $_SESSION['installed'] = true;
                        
                        // ریدایرکت بعد از 3 ثانیه
                        header("Refresh: 3; url=index.php");
                    } else {
                        $error = "خطا در ایجاد فایل پیکربندی";
                    }
                } else {
                    $error = "خطا در ایجاد جداول: " . $conn->error;
                }
            } else {
                $error = "خطا در ایجاد دیتابیس: " . $conn->error;
            }
            $conn->close();
        }
    } catch (Exception $e) {
        $error = "خطا: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سیستم مدیریت بودجه</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .install-header p {
            opacity: 0.9;
        }
        
        .install-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus {
            border-color: #1a73e8;
            outline: none;
        }
        
        .btn-install {
            background: linear-gradient(to left, #1a73e8, #0d47a1);
            color: white;
            border: none;
            padding: 15px;
            font-size: 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-install:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .requirements h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .requirements ul {
            list-style: none;
            padding-right: 20px;
        }
        
        .requirements li {
            margin-bottom: 8px;
            padding-right: 25px;
            position: relative;
        }
        
        .requirements li:before {
            content: '✓';
            position: absolute;
            right: 0;
            color: #28a745;
            font-weight: bold;
        }
        
        .install-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1><i class="fas fa-database"></i> نصب سیستم مدیریت بودجه</h1>
            <p>لطفاً اطلاعات پایگاه داده MySQL را وارد کنید</p>
        </div>
        
        <div class="install-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <p>در حال انتقال به صفحه اصلی...</p>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="requirements">
                <h3>نیازمندی‌های سیستم:</h3>
                <ul>
                    <li>PHP 7.0 یا بالاتر</li>
                    <li>MySQL 5.6 یا بالاتر</li>
                    <li>ماژول PDO فعال</li>
                    <li>دسترسی write به پوشه</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="db_host">میزبان پایگاه داده:</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo $default_db_host; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">نام پایگاه داده:</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo $default_db_name; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">نام کاربری MySQL:</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo $default_db_user; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">رمز عبور MySQL:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?php echo $default_db_pass; ?>">
                </div>
                
                <button type="submit" class="btn-install">
                    <i class="fas fa-download"></i> نصب سیستم
                </button>
            </form>
            
            <div class="install-footer">
                <p>پس از نصب، سیستم به طور خودکار راه‌اندازی می‌شود</p>
            </div>
        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>