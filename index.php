<?php
// index.php
session_start();

// بررسی نصب سیستم
if (!file_exists('config/database.php')) {
    header('Location: setup.php');
    exit;
}

// تست اتصال
require_once 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    $error_message = "خطا در اتصال به پایگاه داده. لطفاً فایل setup.php را اجرا کنید.";
} else {
    // بررسی وجود جداول
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'categories'");
        if ($stmt->rowCount() == 0) {
            $error_message = "جداول پایگاه داده وجود ندارند. لطفاً فایل setup.php را اجرا کنید.";
        }
    } catch(PDOException $e) {
        $error_message = "خطا در بررسی جداول: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="prs" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت بودجه - نسخه پایگاه داده</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .error-banner {
            background: linear-gradient(135deg, #ff6b6b, #c92a2a);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            display: <?php echo isset($error_message) ? 'block' : 'none'; ?>;
        }
        
        .error-banner a {
            color: white;
            text-decoration: underline;
            font-weight: bold;
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .connection-status {
            display: inline-block;
            padding: 5px 15px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .connection-status.connected {
            background: rgba(46, 204, 113, 0.3);
        }
        
        .connection-status.disconnected {
            background: rgba(231, 76, 60, 0.3);
        }
        
        .content-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .form-section, .preview-section {
            flex: 1;
            min-width: 300px;
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        /* Make the preview section scrollable while keeping the load-more button visible */
        .preview-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* configuration: show exactly 3 items before scrolling */
        :root {
            --preview-gap: 15px;
            --item-height: 260px; /* increased so details are more visible */
            --visible-count: 3;
        }

        /* Each budget item gets a fixed height so container can show exactly 3 */
        .budget-item {
            height: var(--item-height);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 14px 16px;
            border: 1px solid #eef2f7;
            border-radius: 8px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }

        .budget-item .item-section {
            padding: 6px 0;
        }

        .budget-item .item-section.header h3 {
            margin: 0;
            font-size: 1.15rem;
            color: #0d47a1;
        }

        .budget-item .item-section.body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .budget-item .budget-desc {
            color: #444;
            font-size: 0.95rem;
            line-height: 1.3;
            overflow: hidden;
        }

        .budget-item .item-section.footer {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .budget-item .category-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 18px;
            font-size: 0.85rem;
            color: #fff;
        }

        #previewContainer {
            overflow-y: auto;
            /* compute max-height = item-height * visible-count + gap*(visible-count - 1) */
            max-height: calc(var(--item-height) * var(--visible-count) + var(--preview-gap) * (var(--visible-count) - 1));
            padding-right: 8px;
        }

        /* Custom scrollbar for WebKit browsers */
        #previewContainer::-webkit-scrollbar {
            width: 10px;
        }

        #previewContainer::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }

        #previewContainer::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 6px;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #1a73e8;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eaeaea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            font-size: 1.3rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            text-align: right;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
            text-align: right;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #1a73e8;
            outline: none;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .amount-input {
            position: relative;
        }
        
        .currency {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: bold;
            color: #666;
        }
        
        #amount {
            padding-left: 15px;
            padding-right: 85px;
            direction: ltr;
            text-align: left;
        }
        
        .btn-submit {
            background: linear-gradient(to left, #1a73e8, #0d47a1);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-submit:hover:not(:disabled) {
            background: linear-gradient(to left, #0d47a1, #1a73e8);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .budget-item {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-right: 4px solid #1a73e8;
            text-align: right;
        }
        
        .budget-item h3 {
            color: #1a73e8;
            margin-bottom: 8px;
        }
        
        .budget-amount {
            font-size: 1.3rem;
            font-weight: bold;
            color: #0d8a4f;
            direction: ltr;
            text-align: left;
        }
        
        .budget-date {
            font-size: 0.85rem;
            color: #777;
            margin-top: 5px;
        }
        
        .empty-preview {
            text-align: center;
            color: #888;
            padding: 30px;
            font-style: italic;
        }
        
        .stats {
            display: flex;
            justify-content: space-between;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .stat-box {
            text-align: center;
            flex: 1;
            padding: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1a73e8;
            direction: ltr;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        footer {
            text-align: center;
            margin-top: 40px;
            color: #777;
            font-size: 0.9rem;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .category-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        
        .category-tag {
            background-color: #e8f0fe;
            color: #1a73e8;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #d2e3fc;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .category-tag:hover {
            background-color: #d2e3fc;
        }
        
        .category-tag.active {
            background-color: #1a73e8;
            color: white;
        }
        
        .new-category-input {
            display: none;
            margin-top: 10px;
        }
        
        .validation-error {
            color: #d32f2f;
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }
        
        .input-error {
            border-color: #d32f2f;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .notification {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 15px 25px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
            max-width: 400px;
        }
        
        .notification.success {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
        }
        
        .notification.error {
            background: linear-gradient(135deg, #f44336, #c62828);
            color: white;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                flex-direction: column;
            }
            
            .stats {
                flex-direction: column;
                gap: 15px;
            }
            
            header h1 {
                font-size: 2rem;
            }
            /* on small screens, reduce visible preview height to 2 items */
            :root {
                --item-height: 150px;
                --visible-count: 2;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error_message)): ?>
            <div class="error-banner">
                <p><?php echo $error_message; ?></p>
                <p><a href="setup.php">کلیک کنید برای راه‌اندازی سیستم</a></p>
            </div>
        <?php endif; ?>
        
        <header>
            <h1><i class="fas fa-database"></i> سیستم مدیریت بودجه</h1>
            <p>ذخیره‌سازی داده‌ها در پایگاه داده MySQL</p>
            <div class="connection-status <?php echo !isset($error_message) ? 'connected' : 'disconnected'; ?>" id="connectionStatus">
                <i class="fas fa-<?php echo !isset($error_message) ? 'check-circle' : 'times-circle'; ?>"></i>
                <?php echo !isset($error_message) ? 'اتصال برقرار' : 'اتصال قطع'; ?>
            </div>
        </header>
        
        <div class="content-wrapper">
            <section class="form-section">
                <h2 class="section-title"><i class="fas fa-plus-circle"></i> آیتم جدید بودجه</h2>
                
                <form id="budgetForm">
                    <div class="form-group">
                        <label for="purpose"><i class="fas fa-hashtag"></i> کد بودجه *</label>
                        <input type="number" step="1" inputmode="numeric" min="0" id="purpose" name="purpose" placeholder="کد عددی بودجه را وارد کنید" required>
                        <div class="validation-error" id="purposeError">لطفاً یک کد عددی معتبر وارد کنید</div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-tags"></i> دسته بندی *</label>
                        <div class="category-tags" id="categoryTags">
                            <!-- دسته‌بندی‌ها از API لود می‌شوند -->
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 10px;">
                            <button type="button" id="addCategoryBtn" class="category-tag" style="background-color: #f0f0f0; color: #666;">
                                <i class="fas fa-plus"></i> دسته جدید
                            </button>
                        </div>
                        <div class="new-category-input" id="newCategoryInput">
                            <input type="text" id="newCategory" placeholder="نام دسته جدید">
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <button type="button" id="saveCategoryBtn" class="btn-submit" style="padding: 10px 20px;">ذخیره</button>
                                <button type="button" id="cancelCategoryBtn" class="btn-submit" style="padding: 10px 20px; background: #666;">لغو</button>
                            </div>
                        </div>
                        <input type="hidden" id="category_id" name="category_id" value="">
                    </div>
                    
                    <div class="form-group">
                        <label for="amount"><i class="fas fa-money-bill-wave"></i> مقدار *</label>
                        <div class="amount-input">
                            <span class="currency">افغانی</span>
                            <input type="text" id="amount" name="amount" placeholder="0" required>
                        </div>
                        <div class="validation-error" id="amountError">لطفاً مقدار معتبر وارد کنید</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-right"></i> توضیحات</label>
                        <textarea id="description" name="description" placeholder="توضیحات اضافی..." required></textarea>
                        <div class="validation-error" id="descriptionError">لطفاً توضیحات را وارد کنید</div>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> اضافه کردن آیتم
                    </button>
                </form>
            </section>
            
            <section class="preview-section">
                <h2 class="section-title"><i class="fas fa-eye"></i> آیتم‌های بودجه</h2>
                <div id="previewContainer">
                    <div class="empty-preview">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p>در حال بارگذاری...</p>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button id="loadMoreBtn" class="btn-submit" style="width: auto; padding: 10px 20px; display: none;">
                        <i class="fas fa-arrow-down"></i> بارگیری بیشتر
                    </button>
                </div>
            </section>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-value" id="totalBudget">0</div>
                <div class="stat-label">مجموع بودجه</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" id="itemCount">0</div>
                <div class="stat-label">تعداد آیتم‌ها</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" id="avgBudget">0</div>
                <div class="stat-label">میانگین</div>
            </div>
        </div>
        
        <footer>
            <p>سیستم مدیریت بودجه با MySQL &copy; ۱۴۰4 | نسخه ۱.۰</p>
        </footer>
    </div>
    
    <div id="notificationContainer"></div>

    <script>
        // متغیرهای سراسری
        // Correct API base for this installation
        const API_BASE = window.location.origin + '/budget/api';
        let categories = [];
        let currentOffset = 0;
        const limit = 5;
        let canLoadMore = false;
        
        // عناصر DOM
        const budgetForm = document.getElementById('budgetForm');
        const previewContainer = document.getElementById('previewContainer');
        const categoryTags = document.getElementById('categoryTags');
        const categoryInput = document.getElementById('category_id');
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const newCategoryInput = document.getElementById('newCategoryInput');
        const newCategory = document.getElementById('newCategory');
        const saveCategoryBtn = document.getElementById('saveCategoryBtn');
        const cancelCategoryBtn = document.getElementById('cancelCategoryBtn');
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        
        // تست اتصال اولیه
        async function testConnection() {
            try {
                const response = await fetch(`${API_BASE}/init.php`);
                const data = await response.json();
                
                if (data.success) {
                    console.log('✅ اتصال موفق:', data.message);
                    return true;
                } else {
                    showNotification('خطا در اتصال به پایگاه داده', 'error');
                    return false;
                }
            } catch (error) {
                console.error('❌ خطا در اتصال:', error);
                showNotification('سرور در دسترس نیست', 'error');
                return false;
            }
        }
        
        // بارگذاری دسته‌بندی‌ها
        async function loadCategories() {
            try {
                const response = await fetch(`${API_BASE}/categories.php`);
                const data = await response.json();
                
                if (data.success) {
                    categories = data.data;
                    renderCategories();
                } else {
                    showNotification('خطا در بارگذاری دسته‌بندی‌ها', 'error');
                }
            } catch (error) {
                console.error('خطا در بارگذاری دسته‌بندی‌ها:', error);
                showNotification('خطا در بارگذاری دسته‌بندی‌ها', 'error');
                
            }
        }
        
        // نمایش دسته‌بندی‌ها
        function renderCategories() {
            categoryTags.innerHTML = '';
            
            categories.forEach(category => {
                const tag = document.createElement('div');
                tag.className = 'category-tag';
                tag.textContent = category.name;
                tag.dataset.id = category.id;
                tag.style.borderLeftColor = category.color || '#1a73e8';
                tag.style.borderLeftWidth = '3px';
                tag.style.borderLeftStyle = 'solid';
                
                tag.addEventListener('click', () => {
                    document.querySelectorAll('.category-tag').forEach(t => {
                        t.classList.remove('active');
                    });
                    tag.classList.add('active');
                    categoryInput.value = category.id;
                });
                
                categoryTags.appendChild(tag);
            });
            
            // انتخاب اولین دسته به صورت پیش‌فرض
            if (categories.length > 0 && !categoryInput.value) {
                categoryTags.firstChild.click();
            }
        }
        
        // بارگذاری آیتم‌های بودجه
        async function loadBudgetItems(reset = true) {
            if (reset) {
                currentOffset = 0;
                previewContainer.innerHTML = `
                    <div class="empty-preview">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p>در حال بارگذاری...</p>
                    </div>
                `;
            }
            
            try {
                const response = await fetch(
                    `${API_BASE}/budget.php?limit=${limit}&offset=${currentOffset}`
                );
                const data = await response.json();
                
                if (data.success) {
                    const items = data.data;
                    const stats = data.stats;
                    
                    if (reset) {
                        previewContainer.innerHTML = '';
                    }
                    
                    if (items.length === 0 && reset) {
                        previewContainer.innerHTML = `
                            <div class="empty-preview">
                                <i class="fas fa-inbox fa-3x" style="color: #ccc;"></i>
                                <p>هنوز هیچ آیتم بودجه اضافه نشده است.</p>
                            </div>
                        `;
                        loadMoreBtn.style.display = 'none';
                    } else {
                        displayBudgetItems(items, reset);
                        canLoadMore = data.pagination.has_more;
                        loadMoreBtn.style.display = canLoadMore ? 'block' : 'none';
                    }
                    
                    // به‌روزرسانی آمار
                    updateStats(stats);
                } else {
                    showNotification('خطا در بارگذاری آیتم‌ها', 'error');
                }
            } catch (error) {
                console.error('خطا در بارگذاری آیتم‌ها:', error);
                previewContainer.innerHTML = `
                    <div class="empty-preview">
                        <i class="fas fa-exclamation-triangle fa-3x" style="color: #dc3545;"></i>
                        <p>خطا در اتصال به سرور</p>
                    </div>
                `;
            }
        }
        
        // نمایش آیتم‌های بودجه
        function displayBudgetItems(items, reset) {
            if (reset) {
                previewContainer.innerHTML = '';
            }
            
            items.forEach(item => {
                const itemElement = document.createElement('div');
                itemElement.className = 'budget-item';
                itemElement.style.borderRightColor = item.category_color || '#1a73e8';
                itemElement.innerHTML = `
                    <div class="item-section header">
                        <h3>کد بودجه: ${item.purpose}</h3>
                    </div>
                    <div class="item-section body">
                        <div class="budget-amount">${formatNumber(item.amount)} افغانی</div>
                        <div><span class="category-badge" style="background:${item.category_color || '#1a73e8'}">${item.category_name}</span></div>
                        ${item.description ? `<div class="budget-desc">${item.description}</div>` : ''}
                    </div>
                    <div class="item-section footer">
                        <div class="budget-date">${formatDate(item.created_at)}</div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button onclick="editItem(this)" class="category-tag" style="background-color: #e8f5e9; color: #1b5e20; border-color: #c8e6c9; font-size: 0.8rem;">
                                <i class="fas fa-edit"></i> ویرایش
                            </button>
                            <button onclick="deleteItem(${item.id})" class="category-tag" style="background-color: #ffebee; color: #d32f2f; border-color: #ffcdd2; font-size: 0.8rem;">
                                <i class="fas fa-trash"></i> حذف
                            </button>
                        </div>
                    </div>
                `;
                previewContainer.appendChild(itemElement);
                // store the item data on the element for editing
                try { itemElement.dataset.item = JSON.stringify(item); } catch(e) { /* ignore */ }
            });
        }
        
        // به‌روزرسانی آمار
        function updateStats(stats) {
            document.getElementById('totalBudget').textContent = formatNumber(stats.total_amount || 0);
            document.getElementById('itemCount').textContent = stats.total_items || 0;
            document.getElementById('avgBudget').textContent = formatNumber(stats.avg_amount || 0);
        }
        
        // حذف آیتم
        async function deleteItem(id) {
            if (!confirm('آیا مطمئن هستید که می‌خواهید این آیتم را حذف کنید؟')) {
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/budget.php`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: id })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('آیتم با موفقیت حذف شد', 'success');
                    loadBudgetItems(true);
                } else {
                    showNotification(data.message || 'خطا در حذف آیتم', 'error');
                }
            } catch (error) {
                showNotification('خطا در اتصال به سرور', 'error');
            }
        }

        // ویرایش آیتم (ویرایش مقدار و توضیحات)
        async function editItem(button) {
            const itemEl = button.closest('.budget-item');
            if (!itemEl || !itemEl.dataset.item) return showNotification('اطلاعات آیتم در دسترس نیست', 'error');
            let item;
            try { item = JSON.parse(itemEl.dataset.item); } catch (e) { return showNotification('خطا در خواندن اطلاعات آیتم', 'error'); }

            // پرس و جو برای مقدار و توضیحات جدید
            const newAmountRaw = prompt('مقدار جدید را وارد کنید:', item.amount);
            if (newAmountRaw === null) return; // cancelled
            const newAmount = parseFloat(String(newAmountRaw).replace(/,/g, ''));
            if (isNaN(newAmount) || newAmount <= 0) return showNotification('لطفاً مقدار عددی معتبر وارد کنید', 'error');

            const newDesc = prompt('توضیحات جدید را وارد کنید (خالی برای حذف):', item.description || '');
            if (newDesc === null) return; // cancelled

            // ارسال درخواست به API
            try {
                const res = await fetch(`${API_BASE}/budget.php`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: item.id, amount: newAmount, description: newDesc })
                });
                const data = await res.json();
                if (data.success) {
                    showNotification('آیتم با موفقیت به‌روزرسانی شد', 'success');
                    // رفرش لیست
                    await loadBudgetItems(true);
                } else {
                    showNotification(data.message || 'خطا در به‌روزرسانی آیتم', 'error');
                }
            } catch (err) {
                showNotification('خطا در اتصال به سرور', 'error');
            }
        }
        
        // ارسال فرم
        budgetForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const purposeRaw = document.getElementById('purpose').value;
            const purposeNum = purposeRaw === '' ? NaN : parseInt(purposeRaw, 10);
            const category_id = document.getElementById('category_id').value;
            const amount = document.getElementById('amount').value;
            const description = document.getElementById('description').value.trim();
            
            // اعتبارسنجی
            if (isNaN(purposeNum)) {
                showValidationError('purposeError', 'لطفاً یک کد عددی معتبر وارد کنید');
                return;
            }
            
            if (!category_id) {
                showNotification('لطفاً یک دسته‌بندی انتخاب کنید', 'error');
                return;
            }
            // validate description (required)
            if (!description || description.length === 0) {
                showValidationError('descriptionError', 'لطفاً توضیحات را وارد کنید');
                return;
            }
            const amountNum = parseFloat(amount.replace(/,/g, ''));
            if (!amount || isNaN(amountNum) || amountNum <= 0) {
                showValidationError('amountError', 'لطفاً مقدار معتبر وارد کنید');
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> در حال ارسال...';
            
            try {
                const response = await fetch(`${API_BASE}/budget.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        purpose: purposeNum,
                        category_id: parseInt(category_id),
                        amount: amountNum,
                        description: description
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('آیتم بودجه با موفقیت اضافه شد', 'success');
                    budgetForm.reset();
                    renderCategories(); // بازنشانی انتخاب دسته
                    loadBudgetItems(true);
                } else {
                    showNotification(data.message || 'خطا در اضافه کردن آیتم', 'error');
                    if (data.errors) {
                        data.errors.forEach(error => {
                            showNotification(error, 'error');
                        });
                    }
                }
            } catch (error) {
                showNotification('خطا در اتصال به سرور', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> اضافه کردن آیتم';
            }
        });
        
        // مدیریت دسته‌بندی جدید
        addCategoryBtn.addEventListener('click', function() {
            newCategoryInput.style.display = 'block';
            this.style.display = 'none';
            newCategory.focus();
        });
        
        cancelCategoryBtn.addEventListener('click', function() {
            newCategoryInput.style.display = 'none';
            addCategoryBtn.style.display = 'block';
            newCategory.value = '';
        });
        
        saveCategoryBtn.addEventListener('click', async function() {
            const name = newCategory.value.trim();
            
            if (!name) {
                showNotification('نام دسته را وارد کنید', 'error');
                return;
            }
            
            try {
                const response = await fetch(`${API_BASE}/categories.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ name: name })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('دسته جدید اضافه شد', 'success');
                    newCategoryInput.style.display = 'none';
                    addCategoryBtn.style.display = 'block';
                    newCategory.value = '';
                    loadCategories();
                } else {
                    showNotification(data.message || 'خطا در اضافه کردن دسته', 'error');
                }
            } catch (error) {
                showNotification('خطا در اتصال به سرور', 'error');
            }
        });
        
        // بارگیری بیشتر
        loadMoreBtn.addEventListener('click', function() {
            currentOffset += limit;
            loadBudgetItems(false);
        });
        
        // اعتبارسنجی ورودی مقدار
        document.getElementById('amount').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d.]/g, '');
            
            // فقط یک نقطه مجاز
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // محدود کردن ارقام اعشار
            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + '.' + parts[1].substring(0, 2);
            }
            
            e.target.value = value;
        });

        // اعتبارسنجی ورودی کد بودجه: اجازه فقط ارقام (عدد صحیح)
        const purposeInput = document.getElementById('purpose');
        purposeInput.addEventListener('input', function(e) {
            // حذف هر کاراکتر غیر رقمی
            const cleaned = String(e.target.value).replace(/[^0-9]/g, '');
            if (e.target.value !== cleaned) {
                e.target.value = cleaned;
            }
        });

        // پاک‌سازی محتوای پیست شده تا فقط ارقام نگه‌داشته شود
        purposeInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const cleaned = paste.replace(/[^0-9]/g, '');
            const el = e.target;
            const start = el.selectionStart;
            const end = el.selectionEnd;
            const newVal = el.value.slice(0, start) + cleaned + el.value.slice(end);
            el.value = newVal;
            // trigger input event
            el.dispatchEvent(new Event('input'));
        });

        // جلوگیری از وارد کردن کاراکترهای غیرعددی با کی‌پرس
        purposeInput.addEventListener('keydown', function(e) {
            // اجازه کلیدهای کنترل و ارقام
            const allowedKeys = ['Backspace','ArrowLeft','ArrowRight','Delete','Tab','Home','End'];
            if (allowedKeys.indexOf(e.key) !== -1) return;
            // Allow Ctrl/Cmd shortcuts
            if (e.ctrlKey || e.metaKey) return;
            // Allow numpad digits and digits
            if (/^[0-9]$/.test(e.key)) return;
            e.preventDefault();
        });
        
        // فرمت اعداد
        function formatNumber(num) {
            return new Intl.NumberFormat('fa-IR').format(num);
        }
        
        // فرمت تاریخ — تبدیل به تقویم جلالی و نمایش نام ماه به دری
        function gregorianToJalali(gy, gm, gd) {
            var g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
            var gy2 = gy - 1600;
            var gm2 = gm - 1;
            var gd2 = gd - 1;
            var g_day_no = 365 * gy2 + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400);
            g_day_no += g_d_m[gm2] + gd2;
            if (gm2 > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0))) g_day_no++;
            var j_day_no = g_day_no - 79;
            var j_np = Math.floor(j_day_no / 12053);
            j_day_no = j_day_no % 12053;
            var jy = 979 + 33 * j_np + 4 * Math.floor(j_day_no / 1461);
            j_day_no = j_day_no % 1461;
            if (j_day_no >= 366) {
                jy += Math.floor((j_day_no - 366) / 365);
                j_day_no = (j_day_no - 366) % 365;
            }
            var jm = 0;
            var jd = 0;
            var jalali_months_days = [31,31,31,31,31,31,30,30,30,30,30,29];
            for (var i = 0; i < 12; i++) {
                if (j_day_no < jalali_months_days[i]) {
                    jm = i + 1;
                    jd = j_day_no + 1;
                    break;
                }
                j_day_no -= jalali_months_days[i];
            }
            return [jy, jm, jd];
        }

        function pad2(n){ return n < 10 ? '0' + n : n; }

        // Dari month names (Jalali months)
        const DARI_MONTHS = ['حمل','ثور','جوزا','سرطان','اسد','سنبله','میزان','عقرب','قوس','جدی','دلو','حوت'];

        function formatDate(dateString) {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            const gy = date.getFullYear();
            const gm = date.getMonth() + 1;
            const gd = date.getDate();
            const hours = pad2(date.getHours());
            const minutes = pad2(date.getMinutes());
            const j = gregorianToJalali(gy, gm, gd);
            const jy = j[0], jm = j[1], jd = j[2];
            const monthName = DARI_MONTHS[jm - 1] || '';
            return `${jd} ${monthName} ${jy} - ${hours}:${minutes}`;
        }
        
        // نمایش خطای اعتبارسنجی
        function showValidationError(elementId, message) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.style.display = 'block';
            setTimeout(() => {
                element.style.display = 'none';
            }, 3000);
        }
        
        // نمایش اعلان
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            
            container.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => {
                    if (notification.parentNode) {
                        container.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        // راه‌اندازی اولیه
        document.addEventListener('DOMContentLoaded', async function() {
            // تست اتصال
            const connected = await testConnection();
            
            if (connected) {
                // بارگذاری داده‌ها
                await loadCategories();
                await loadBudgetItems();
                
                // بروزرسانی وضعیت اتصال
                const statusElement = document.getElementById('connectionStatus');
                statusElement.className = 'connection-status connected';
                statusElement.innerHTML = '<i class="fas fa-check-circle"></i> اتصال برقرار';
            }
        });
    </script>
</body>
</html>