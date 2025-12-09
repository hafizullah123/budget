<?php
// FILE: models/Budget.php
// PURPOSE: مدیریت عملیات دیتابیس مربوط به بودجه

class Budget {
    private $conn;
    private $table_periods = 'دوره_های_بودجه';
    private $table_lines = 'سطرهای_بودجه';
    private $table_accounts = 'چارت_حسابها';

    public function __construct($db) {
        $this->conn = $db;
    }

    // ============================================
    // 1. ایجاد دوره جدید بودجه
    // PURPOSE: آغاز یک دوره بودجه جدید (ماهیانه، سهماهه، سالانه)
    // ============================================
    public function createBudgetPeriod($data) {
        try {
            $query = "INSERT INTO " . $this->table_periods . " 
                      (نام_دوره, نوع_دوره, تاریخ_شروع, تاریخ_پایان, وضعیت, ایجاد_کننده) 
                      VALUES (:نام_دوره, :نوع_دوره, :تاریخ_شروع, :تاریخ_پایان, 'پیش‌نویس', :ایجاد_کننده)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':نام_دوره', $data['نام_دوره']);
            $stmt->bindParam(':نوع_دوره', $data['نوع_دوره']);
            $stmt->bindParam(':تاریخ_شروع', $data['تاریخ_شروع']);
            $stmt->bindParam(':تاریخ_پایان', $data['تاریخ_پایان']);
            $stmt->bindParam(':ایجاد_کننده', $data['ایجاد_کننده']);
            
            if($stmt->execute()) {
                return [
                    'موفقیت' => true,
                    'پیام' => 'دوره بودجه با موفقیت ایجاد شد',
                    'هدف' => 'آغاز دوره بودجه برای مدت زمان مشخص',
                    'شناسه' => $this->conn->lastInsertId()
                ];
            }
            
            return ['موفقیت' => false, 'پیام' => 'ایجاد دوره بودجه ناموفق بود'];
            
        } catch(PDOException $e) {
            return ['موفقیت' => false, 'پیام' => 'خطای دیتابیس: ' . $e->getMessage()];
        }
    }

    // ============================================
    // 2. ورود دستی سطر بودجه
    // PURPOSE: وارد کردن یا بروزرسانی آیتم‌های سطر بودجه
    // ============================================
    public function addBudgetLineManual($data) {
        try {
            // بررسی وجود سطر بودجه
            $checkQuery = "SELECT شناسه FROM " . $this->table_lines . " 
                           WHERE دوره_بودجه_شناسه = :دوره_شناسه 
                           AND حساب_شناسه = :حساب_شناسه";
            
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':دوره_شناسه', $data['دوره_بودجه_شناسه']);
            $checkStmt->bindParam(':حساب_شناسه', $data['حساب_شناسه']);
            $checkStmt->execute();
            
            if($checkStmt->rowCount() > 0) {
                // بروزرسانی موجود
                $result = $checkStmt->fetch();
                $query = $this->buildUpdateQuery($data);
                $stmt = $this->conn->prepare($query);
                $this->bindUpdateParams($stmt, $data);
                $stmt->bindParam(':شناسه', $result['شناسه']);
                
                $action = 'بروزرسانی شد';
            } else {
                // وارد کردن جدید
                $query = $this->buildInsertQuery($data);
                $stmt = $this->conn->prepare($query);
                $this->bindInsertParams($stmt, $data);
                
                $action = 'ایجاد شد';
            }
            
            if($stmt->execute()) {
                return [
                    'موفقیت' => true,
                    'پیام' => 'سطر بودجه ' . $action,
                    'هدف' => $action == 'ایجاد شد' ? 
                            'ایجاد تخصیص بودجه جدید برای حساب' : 
                            'بروزرسانی تخصیص بودجه موجود'
                ];
            }
            
            return ['موفقیت' => false, 'پیام' => 'ذخیره سطر بودجه ناموفق بود'];
            
        } catch(PDOException $e) {
            return ['موفقیت' => false, 'پیام' => 'خطای دیتابیس: ' . $e->getMessage()];
        }
    }

    private function buildInsertQuery($data) {
        $fields = ['دوره_بودجه_شناسه', 'حساب_شناسه'];
        $placeholders = [':دوره_بودجه_شناسه', ':حساب_شناسه'];
        $values = [];
        
        // افزودن مقادیر ماهانه
        $months = ['جنوری', 'فبروری', 'مارچ', 'اپریل', 'می', 'جون',
                  'جولای', 'آگست', 'سپتمبر', 'اکتوبر', 'نومبر', 'دسمبر'];
        
        $persian_months = ['حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله',
                          'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'];
        
        foreach($months as $index => $month) {
            $field_name = 'مقدار_' . $persian_months[$index];
            if(isset($data[$field_name])) {
                $fields[] = $field_name;
                $placeholders[] = ':' . $field_name;
                $values[$field_name] = $data[$field_name];
            }
        }
        
        // افزودن فیلدهای اختیاری
        $optional = ['دپارتمان_شناسه', 'پروژه_شناسه', 'یادداشت'];
        foreach($optional as $field) {
            if(isset($data[$field])) {
                $fields[] = $field;
                $placeholders[] = ':' . $field;
            }
        }
        
        return "INSERT INTO " . $this->table_lines . " (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
    }

    private function bindInsertParams(&$stmt, $data) {
        $stmt->bindParam(':دوره_بودجه_شناسه', $data['دوره_بودجه_شناسه']);
        $stmt->bindParam(':حساب_شناسه', $data['حساب_شناسه']);
        
        $persian_months = ['حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله',
                          'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'];
        
        foreach($persian_months as $month) {
            $field_name = 'مقدار_' . $month;
            if(isset($data[$field_name])) {
                $stmt->bindParam(':' . $field_name, $data[$field_name]);
            }
        }
        
        if(isset($data['دپارتمان_شناسه'])) $stmt->bindParam(':دپارتمان_شناسه', $data['دپارتمان_شناسه']);
        if(isset($data['پروژه_شناسه'])) $stmt->bindParam(':پروژه_شناسه', $data['پروژه_شناسه']);
        if(isset($data['یادداشت'])) $stmt->bindParam(':یادداشت', $data['یادداشت']);
    }

    // ============================================
    // 3. ورود گروهی بودجه از CSV
    // PURPOSE: وارد کردن چندین سطر بودجه یکجا
    // ============================================
    public function bulkBudgetEntry($دوره_بودجه_شناسه, $entries) {
        try {
            $موفق_شماری = 0;
            $ناموفق_شماری = 0;
            $خطاها = [];
            
            // شروع تراکنش
            $this->conn->beginTransaction();
            
            foreach($entries as $index => $entry) {
                try {
                    // اعتبارسنجی فیلدهای ضروری
                    if(!isset($entry['حساب_شناسه']) || !$entry['حساب_شناسه']) {
                        throw new Exception("شناسه حساب در ردیف " . ($index + 1) . " موجود نیست");
                    }
                    
                    // آماده سازی داده برای وارد کردن/بروزرسانی
                    $budgetData = [
                        'دوره_بودجه_شناسه' => $دوره_بودجه_شناسه,
                        'حساب_شناسه' => $entry['حساب_شناسه']
                    ];
                    
                    // افزودن مقادیر ماهانه اگر موجود باشد
                    if(isset($entry['مقادیر_ماهانه']) && is_array($entry['مقادیر_ماهانه'])) {
                        foreach($entry['مقادیر_ماهانه'] as $month => $amount) {
                            $budgetData['مقدار_' . $month] = $amount;
                        }
                    }
                    
                    // استفاده از متد موجود برای وارد کردن/بروزرسانی
                    $result = $this->addBudgetLineManual($budgetData);
                    
                    if($result['موفقیت']) {
                        $موفق_شماری++;
                    } else {
                        $ناموفق_شماری++;
                        $خطاها[] = "ردیف " . ($index + 1) . ": " . $result['پیام'];
                    }
                    
                } catch(Exception $e) {
                    $ناموفق_شماری++;
                    $خطاها[] = "ردیف " . ($index + 1) . ": " . $e->getMessage();
                }
            }
            
            // ثبت تراکنش
            $this->conn->commit();
            
            return [
                'موفقیت' => true,
                'پیام' => "ورود گروهی تکمیل شد",
                'خلاصه' => [
                    'کل_پردازش' => count($entries),
                    'موفق' => $موفق_شماری,
                    'ناموفق' => $ناموفق_شماری,
                    'خطاها' => $خطاها
                ],
                'هدف' => 'وارد کردن چندین تخصیص بودجه از CSV/Excel یکجا'
            ];
            
        } catch(PDOException $e) {
            $this->conn->rollBack();
            return ['موفقیت' => false, 'پیام' => 'تراکنش ناموفق: ' . $e->getMessage()];
        }
    }

    // ============================================
    // 4. کپی بودجه از دوره قبلی
    // PURPOSE: کپی کردن سطرهای بودجه از دوره قبلی با تنظیم اختیاری
    // ============================================
    public function copyFromPreviousPeriod($منبع_شناسه, $هدف_شناسه, $درصد_تنظیم = 0) {
        try {
            // دریافت سطرهای بودجه منبع
            $query = "SELECT * FROM " . $this->table_lines . " 
                      WHERE دوره_بودجه_شناسه = :منبع_شناسه";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':منبع_شناسه', $منبع_شناسه);
            $stmt->execute();
            
            $sourceLines = $stmt->fetchAll();
            
            if(count($sourceLines) == 0) {
                return ['موفقیت' => false, 'پیام' => 'هیچ سطر بودجه‌ای در دوره منبع یافت نشد'];
            }
            
            $کپی_شماری = 0;
            $تنظیم = 1 + ($درصد_تنظیم / 100);
            $persian_months = ['حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله',
                              'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'];
            
            // شروع تراکنش
            $this->conn->beginTransaction();
            
            foreach($sourceLines as $line) {
                $insertQuery = "INSERT INTO " . $this->table_lines . " 
                                (دوره_بودجه_شناسه, حساب_شناسه, دپارتمان_شناسه, پروژه_شناسه,
                                 مقدار_حمل, مقدار_ثور, مقدار_جوزا, مقدار_سرطان, 
                                 مقدار_اسد, مقدار_سنبله, مقدار_میزان, مقدار_عقرب,
                                 مقدار_قوس, مقدار_جدی, مقدار_دلو, مقدار_حوت,
                                 یادداشت)
                                VALUES 
                                (:هدف_شناسه, :حساب_شناسه, :دپارتمان_شناسه, :پروژه_شناسه,
                                 :حمل, :ثور, :جوزا, :سرطان, :اسد, :سنبله, 
                                 :میزان, :عقرب, :قوس, :جدی, :دلو, :حوت, :یادداشت)";
                
                $insertStmt = $this->conn->prepare($insertQuery);
                
                // بایند کردن پارامترها با تنظیم
                $insertStmt->bindParam(':هدف_شناسه', $هدف_شناسه);
                $insertStmt->bindParam(':حساب_شناسه', $line['حساب_شناسه']);
                $insertStmt->bindParam(':دپارتمان_شناسه', $line['دپارتمان_شناسه']);
                $insertStmt->bindParam(':پروژه_شناسه', $line['پروژه_شناسه']);
                $insertStmt->bindParam(':یادداشت', $line['یادداشت']);
                
                // بایند کردن مقادیر ماهانه با تنظیم
                foreach($persian_months as $month) {
                    $field_name = 'مقدار_' . $month;
                    $adjustedAmount = ($line[$field_name] ?? 0) * $تنظیم;
                    $insertStmt->bindParam(':' . $month, $adjustedAmount);
                }
                
                if($insertStmt->execute()) {
                    $کپی_شماری++;
                }
            }
            
            $this->conn->commit();
            
            return [
                'موفقیت' => true,
                'پیام' => "$کپی_شماری سطر بودجه کپی شد",
                'سطرهای_کپی_شده' => $کپی_شماری,
                'تنظیم_اعمال_شده' => $درصد_تنظیم . '%',
                'هدف' => 'کپی کردن بودجه قبلی به عنوان الگو برای دوره جدید'
            ];
            
        } catch(PDOException $e) {
            $this->conn->rollBack();
            return ['موفقیت' => false, 'پیام' => 'کپی ناموفق: ' . $e->getMessage()];
        }
    }

    // ============================================
    // 5. دریافت بودجه برای تحلیل
    // PURPOSE: بازیابی داده‌های بودجه برای گزارش‌گیری و تحلیل
    // ============================================
    public function getBudgetAnalysis($دوره_شناسه, $filters = []) {
        try {
            $query = "
                SELECT 
                    bl.*,
                    ca.کد_حساب,
                    ca.نام_حساب,
                    ca.نوع_حساب,
                    d.نام_دپارتمان,
                    p.نام_پروژه,
                    bp.نام_دوره,
                    bp.نوع_دوره,
                    bp.تاریخ_شروع,
                    bp.تاریخ_پایان
                FROM " . $this->table_lines . " bl
                JOIN " . $this->table_accounts . " ca ON bl.حساب_شناسه = ca.شناسه
                JOIN " . $this->table_periods . " bp ON bl.دوره_بودجه_شناسه = bp.شناسه
                LEFT JOIN دپارتمانها d ON bl.دپارتمان_شناسه = d.شناسه
                LEFT JOIN پروژه‌ها p ON bl.پروژه_شناسه = p.شناسه
                WHERE bl.دوره_بودجه_شناسه = :دوره_شناسه
            ";
            
            $params = [':دوره_شناسه' => $دوره_شناسه];
            
            // افزودن فیلترها
            if(isset($filters['دپارتمان_شناسه'])) {
                $query .= " AND bl.دپارتمان_شناسه = :دپارتمان_شناسه";
                $params[':دپارتمان_شناسه'] = $filters['دپارتمان_شناسه'];
            }
            
            if(isset($filters['پروژه_شناسه'])) {
                $query .= " AND bl.پروژه_شناسه = :پروژه_شناسه";
                $params[':پروژه_شناسه'] = $filters['پروژه_شناسه'];
            }
            
            if(isset($filters['نوع_حساب'])) {
                $query .= " AND ca.نوع_حساب = :نوع_حساب";
                $params[':نوع_حساب'] = $filters['نوع_حساب'];
            }
            
            $query .= " ORDER BY ca.کد_حساب";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            // محاسبه آمار خلاصه
            $summary = [
                'کل_بودجه' => 0,
                'براساس_نوع_حساب' => [],
                'براساس_دپارتمان' => [],
                'براساس_ماه' => [
                    'حمل' => 0, 'ثور' => 0, 'جوزا' => 0, 'سرطان' => 0,
                    'اسد' => 0, 'سنبله' => 0, 'میزان' => 0, 'عقرب' => 0,
                    'قوس' => 0, 'جدی' => 0, 'دلو' => 0, 'حوت' => 0
                ]
            ];
            
            $persian_months = ['حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله',
                              'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'];
            
            foreach($results as $row) {
                // محاسبه کل سالانه
                $annualTotal = 0;
                foreach($persian_months as $month) {
                    $annualTotal += $row['مقدار_' . $month] ?? 0;
                }
                
                $summary['کل_بودجه'] += $annualTotal;
                
                // براساس نوع حساب
                $accountType = $row['نوع_حساب'] ?? 'نامشخص';
                $summary['براساس_نوع_حساب'][$accountType] = 
                    ($summary['براساس_نوع_حساب'][$accountType] ?? 0) + $annualTotal;
                
                // براساس دپارتمان
                $dept = $row['نام_دپارتمان'] ?? 'تخصیص‌نشده';
                $summary['براساس_دپارتمان'][$dept] = 
                    ($summary['براساس_دپارتمان'][$dept] ?? 0) + $annualTotal;
                
                // براساس ماه
                foreach($persian_months as $month) {
                    $summary['براساس_ماه'][$month] += $row['مقدار_' . $month] ?? 0;
                }
            }
            
            return [
                'موفقیت' => true,
                'داده' => $results,
                'خلاصه' => $summary,
                'هدف' => 'بازیابی داده‌های بودجه برای تحلیل و گزارش'
            ];
            
        } catch(PDOException $e) {
            return ['موفقیت' => false, 'پیام' => 'خطای بازیابی: ' . $e->getMessage()];
        }
    }
}

// FILE: controllers/BudgetController.php
// PURPOSE: کنترلر برای مدیریت درخواست‌های بودجه

class BudgetController {
    private $budgetModel;
    
    public function __construct($db) {
        $this->budgetModel = new Budget($db);
    }
    
    public function handleRequest($action) {
        header('Content-Type: application/json; charset=utf-8');
        
        switch($action) {
            case 'create_period':
                $data = json_decode(file_get_contents('php://input'), true);
                $response = $this->budgetModel->createBudgetPeriod($data);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'add_line':
                $data = json_decode(file_get_contents('php://input'), true);
                $response = $this->budgetModel->addBudgetLineManual($data);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'bulk_entry':
                $data = json_decode(file_get_contents('php://input'), true);
                if(isset($data['دوره_بودجه_شناسه']) && isset($data['ورودی‌ها'])) {
                    $response = $this->budgetModel->bulkBudgetEntry(
                        $data['دوره_بودجه_شناسه'],
                        $data['ورودی‌ها']
                    );
                } else {
                    $response = ['موفقیت' => false, 'پیام' => 'داده‌های ناقص'];
                }
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'copy_period':
                $data = json_decode(file_get_contents('php://input'), true);
                if(isset($data['منبع_شناسه']) && isset($data['هدف_شناسه'])) {
                    $adjustment = $data['درصد_تنظیم'] ?? 0;
                    $response = $this->budgetModel->copyFromPreviousPeriod(
                        $data['منبع_شناسه'],
                        $data['هدف_شناسه'],
                        $adjustment
                    );
                } else {
                    $response = ['موفقیت' => false, 'پیام' => 'شناسه‌های دوره ضروری هستند'];
                }
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'get_analysis':
                $دوره_شناسه = $_GET['دوره_شناسه'] ?? null;
                $filters = [
                    'دپارتمان_شناسه' => $_GET['دپارتمان_شناسه'] ?? null,
                    'پروژه_شناسه' => $_GET['پروژه_شناسه'] ?? null,
                    'نوع_حساب' => $_GET['نوع_حساب'] ?? null
                ];
                
                if($دوره_شناسه) {
                    $response = $this->budgetModel->getBudgetAnalysis($دوره_شناسه, $filters);
                } else {
                    $response = ['موفقیت' => false, 'پیام' => 'شناسه دوره ضروری است'];
                }
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                echo json_encode(['موفقیت' => false, 'پیام' => 'عمل نامعتبر'], JSON_UNESCAPED_UNICODE);
        }
    }
}

// USAGE EXAMPLE:
/*
$database = new Database();
$db = $database->connect();

$controller = new BudgetController($db);

// Get action from URL parameter
$action = $_GET['action'] ?? '';
$controller->handleRequest($action);
*/
?>