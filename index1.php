<?php
// index.php - Main entry point
session_start();

// Database connection
$host = 'localhost';
$dbname = 'accounting_software';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// Handle API requests
if (isset($_GET['api'])) {
    require_once 'api.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت بودجه - نرم افزار حسابداری</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Persian Fonts -->
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    
    <!-- Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * {
            font-family: 'Vazirmatn', sans-serif;
        }
        
        .sidebar {
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed .menu-text {
            display: none;
        }
        
        .budget-table th {
            position: sticky;
            top: 0;
            background-color: #1e40af;
            color: white;
            z-index: 10;
        }
        
        .month-cell {
            min-width: 120px;
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-table {
                border: 1px solid #000 !important;
            }
            
            .print-table th {
                background-color: #f0f0f0 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen">
    
    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center hidden">
        <div class="text-center">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-600 text-lg">در حال بارگذاری...</p>
            <p class="text-gray-500 text-sm mt-2" id="loading-message">لطفا چند لحظه صبر کنید</p>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notification-container" class="fixed top-4 left-4 z-50 space-y-2"></div>
    
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar fixed right-0 top-0 h-full bg-gradient-to-b from-blue-900 to-blue-800 shadow-2xl z-40 w-64 text-white">
        <!-- Logo -->
        <div class="p-6 border-b border-blue-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="bg-white text-blue-700 p-3 rounded-xl shadow-lg ml-3">
                        <i class="fas fa-coins text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">سیستم بودجه</h1>
                        <p class="text-blue-200 text-xs">مدیریت مالی هوشمند</p>
                    </div>
                </div>
                <button id="toggleSidebar" class="text-blue-200 hover:text-white">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- User Info -->
        <div class="p-4 border-b border-blue-700">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-400 rounded-full flex items-center justify-center ml-3 shadow-lg">
                    <i class="fas fa-user text-white text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="font-semibold">مدیر سیستم</p>
                    <p class="text-blue-200 text-sm">سطح دسترسی: ادمین</p>
                </div>
            </div>
        </div>
        
        <!-- Main Menu -->
        <nav class="p-4 space-y-1">
            <a href="#" data-page="dashboard" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200 active">
                <i class="fas fa-home ml-3"></i>
                <span class="menu-text">داشبورد</span>
                <span class="mr-auto bg-blue-600 text-xs px-2 py-1 rounded-full">1</span>
            </a>
            
            <a href="#" data-page="budget-periods" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200">
                <i class="fas fa-calendar-alt ml-3"></i>
                <span class="menu-text">دوره‌های بودجه</span>
            </a>
            
            <a href="#" data-page="manual-entry" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200">
                <i class="fas fa-edit ml-3"></i>
                <span class="menu-text">ورود دستی بودجه</span>
                <span class="mr-auto bg-green-500 text-xs px-2 py-1 rounded-full">جدید</span>
            </a>
            
            <a href="#" data-page="budget-analysis" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200">
                <i class="fas fa-chart-line ml-3"></i>
                <span class="menu-text">تحلیل بودجه</span>
            </a>
            
            <a href="#" data-page="reports" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200">
                <i class="fas fa-file-alt ml-3"></i>
                <span class="menu-text">گزارشات</span>
            </a>
            
            <!-- Sub Menu -->
            <div class="pt-4 mt-4 border-t border-blue-700">
                <p class="text-blue-300 text-xs font-semibold mb-2 px-3">تنظیمات سیستم</p>
                <a href="#" data-page="accounts" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200">
                    <i class="fas fa-book ml-3"></i>
                    <span class="menu-text">چارت حساب‌ها</span>
                </a>
                
                <a href="#" data-page="departments" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200">
                    <i class="fas fa-building ml-3"></i>
                    <span class="menu-text">دپارتمان‌ها</span>
                </a>
                
                <a href="#" data-page="projects" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200">
                    <i class="fas fa-project-diagram ml-3"></i>
                    <span class="menu-text">پروژه‌ها</span>
                </a>
            </div>
        </nav>
        
        <!-- Bottom Section -->
        <div class="absolute bottom-0 w-full p-4 border-t border-blue-700 bg-blue-900">
            <div class="space-y-2">
                <a href="#" data-page="settings" class="flex items-center p-2 text-blue-100 hover:bg-blue-800 rounded-lg">
                    <i class="fas fa-cog ml-2"></i>
                    <span class="text-sm">تنظیمات</span>
                </a>
                
                <a href="#" id="logout-btn" class="flex items-center p-2 text-red-200 hover:bg-red-900 rounded-lg">
                    <i class="fas fa-sign-out-alt ml-2"></i>
                    <span class="text-sm">خروج از سیستم</span>
                </a>
                
                <div class="text-center pt-2">
                    <p class="text-blue-300 text-xs">نسخه ۱.۰.۰</p>
                    <p class="text-blue-400 text-xs">۱۴۰۳ ©</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div id="main-content" class="mr-64 transition-all duration-300">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm border-b">
            <div class="px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 id="page-title" class="text-xl font-bold text-gray-800">داشبورد سیستم</h2>
                        <p id="page-subtitle" class="text-gray-600 text-sm">خلاصه وضعیت مالی و بودجه‌ای</p>
                    </div>
                    
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <!-- Quick Stats -->
                        <div class="hidden md:flex items-center space-x-6 space-x-reverse">
                            <div class="text-right">
                                <p class="text-gray-500 text-xs">بودجه کل</p>
                                <p class="text-lg font-bold text-blue-700" id="header-total-budget">۰ افغانی</p>
                            </div>
                            <div class="text-right">
                                <p class="text-gray-500 text-xs">دوره فعال</p>
                                <p class="text-lg font-bold text-green-700" id="header-active-period">بدون دوره</p>
                            </div>
                        </div>
                        
                        <!-- Notifications -->
                        <div class="relative">
                            <button id="notifications-btn" class="relative p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg">
                                <i class="fas fa-bell text-xl"></i>
                                <span class="absolute top-0 left-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center animate-pulse">۳</span>
                            </button>
                        </div>
                        
                        <!-- Search -->
                        <div class="relative hidden md:block">
                            <input type="text" id="global-search" placeholder="جستجو در سیستم..." 
                                   class="p-2 pr-10 border border-gray-300 rounded-lg w-64 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                        
                        <!-- Date Display -->
                        <div class="text-left border-r border-gray-300 pr-4">
                            <p class="text-gray-700 font-medium" id="current-date">--</p>
                            <p class="text-gray-500 text-sm" id="current-time">--:--</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Breadcrumb -->
        <div class="bg-gradient-to-r from-blue-50 to-gray-50 px-6 py-3 border-b">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 space-x-reverse md:space-x-3 md:space-x-reverse">
                    <li class="inline-flex items-center">
                        <a href="#" data-page="dashboard" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                            <i class="fas fa-home ml-1"></i>
                            خانه
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-left text-gray-400"></i>
                            <span id="breadcrumb-current" class="mr-1 text-sm font-medium text-gray-700">داشبورد</span>
                        </div>
                    </li>
                </ol>
            </nav>
        </div>
        
        <!-- Main Content Area -->
        <main class="p-6">
            <div id="content-area">
                <!-- Content will be loaded here dynamically -->
                <div id="dashboard-content" class="content-section">
                    <!-- Dashboard content will be loaded by JavaScript -->
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-t px-6 py-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-2 md:mb-0">
                    <p class="text-gray-600 text-sm">
                        <i class="fas fa-database ml-1"></i>
                        <span id="connection-status" class="text-green-600">متصل به دیتابیس</span>
                    </p>
                </div>
                <div class="text-center md:text-left">
                    <p class="text-gray-500 text-sm">
                        سیستم مدیریت بودجه - طراحی شده برای سازمان‌های افغانستانی
                    </p>
                </div>
                <div class="mt-2 md:mt-0">
                    <p class="text-gray-600 text-sm">
                        <i class="fas fa-clock ml-1"></i>
                        آخرین به‌روزرسانی: <span id="last-update">هم اکنون</span>
                    </p>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Modals Container -->
    <div id="modals-container"></div>
    
    <!-- JavaScript -->
    <script>
        // Global variables
        const API_URL = 'index.php?api=true';
        let currentUser = {
            id: 1,
            name: 'مدیر سیستم',
            role: 'admin'
        };
        
        let currentPeriod = null;
        let budgetData = {};
        
        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date and time
            updateDateTime();
            setInterval(updateDateTime, 60000);
            
            // Load dashboard on startup
            loadDashboard();
            
            // Set up event listeners
            setupEventListeners();
            
            // Load system stats
            loadSystemStats();
            
            // Check connection
            checkDatabaseConnection();
        });
        
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                weekday: 'long'
            };
            
            const persianDate = toPersianDate(now);
            const time = now.toLocaleTimeString('fa-IR');
            
            document.getElementById('current-date').textContent = persianDate;
            document.getElementById('current-time').textContent = time;
        }
        
        // Convert to Persian date
        function toPersianDate(date) {
            const persianMonths = [
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
            ];
            
            const persianDays = [
                'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه',
                'پنجشنبه', 'جمعه', 'شنبه'
            ];
            
            // Simple conversion for demo
            const day = date.getDate();
            const month = persianMonths[date.getMonth()];
            const year = date.getFullYear() - 621; // Approximate conversion
            
            return `${day} ${month} ${year}`;
        }
        
        // Setup event listeners
        function setupEventListeners() {
            // Sidebar toggle
            document.getElementById('toggleSidebar').addEventListener('click', toggleSidebar);
            
            // Menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.getAttribute('data-page');
                    loadPage(page);
                    
                    // Update active menu item
                    document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active', 'bg-blue-800'));
                    this.classList.add('active', 'bg-blue-800');
                });
            });
            
            // Logout button
            document.getElementById('logout-btn').addEventListener('click', function(e) {
                e.preventDefault();
                showConfirmModal('خروج از سیستم', 'آیا مطمئن هستید که می‌خواهید از سیستم خارج شوید؟', logout);
            });
            
            // Global search
            document.getElementById('global-search').addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    performSearch(this.value);
                }
            });
            
            // Notifications button
            document.getElementById('notifications-btn').addEventListener('click', showNotifications);
        }
        
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            
            sidebar.classList.toggle('collapsed');
            if (sidebar.classList.contains('collapsed')) {
                mainContent.classList.remove('mr-64');
                mainContent.classList.add('mr-20');
            } else {
                mainContent.classList.remove('mr-20');
                mainContent.classList.add('mr-64');
            }
        }
        
        // Load page content
        function loadPage(page) {
            showLoading('در حال بارگذاری...');
            
            // Update breadcrumb
            const pageTitles = {
                'dashboard': 'داشبورد',
                'budget-periods': 'دوره‌های بودجه',
                'manual-entry': 'ورود دستی بودجه',
                'budget-analysis': 'تحلیل بودجه',
                'reports': 'گزارشات',
                'accounts': 'چارت حساب‌ها',
                'departments': 'دپارتمان‌ها',
                'projects': 'پروژه‌ها',
                'settings': 'تنظیمات'
            };
            
            document.getElementById('page-title').textContent = pageTitles[page] || page;
            document.getElementById('page-subtitle').textContent = 'مدیریت سیستم بودجه‌ریزی';
            document.getElementById('breadcrumb-current').textContent = pageTitles[page] || page;
            
            // Load page content
            const contentArea = document.getElementById('content-area');
            
            switch(page) {
                case 'dashboard':
                    loadDashboard();
                    break;
                case 'budget-periods':
                    loadBudgetPeriods();
                    break;
                case 'manual-entry':
                    loadManualEntry();
                    break;
                case 'budget-analysis':
                    loadBudgetAnalysis();
                    break;
                case 'reports':
                    loadReports();
                    break;
                case 'accounts':
                    loadAccounts();
                    break;
                case 'departments':
                    loadDepartments();
                    break;
                case 'projects':
                    loadProjects();
                    break;
                case 'settings':
                    loadSettings();
                    break;
                default:
                    contentArea.innerHTML = `
                        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                            <i class="fas fa-cogs text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-700 mb-2">صفحه در حال توسعه</h3>
                            <p class="text-gray-600 mb-6">این بخش در حال توسعه و تکمیل می‌باشد.</p>
                            <button onclick="loadDashboard()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                                بازگشت به داشبورد
                            </button>
                        </div>
                    `;
                    hideLoading();
            }
        }
        
        // Load dashboard
        async function loadDashboard() {
            showLoading('در حال بارگذاری داشبورد...');
            
            try {
                const response = await fetch(`${API_URL}&action=dashboard_stats`);
                const data = await response.json();
                
                if (data.success) {
                    renderDashboard(data.data);
                } else {
                    showError('خطا در بارگذاری داشبورد');
                }
            } catch (error) {
                console.error('Error loading dashboard:', error);
                showError('خطا در ارتباط با سرور');
            }
            
            hideLoading();
        }
        
        // Render dashboard
        function renderDashboard(data) {
            const contentArea = document.getElementById('content-area');
            
            contentArea.innerHTML = `
                <div class="animate-fade-in">
                    <!-- Quick Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6 card-hover">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-blue-100 text-sm">کل بودجه ثبت شده</p>
                                    <p class="text-2xl font-bold mt-2">${formatCurrency(data.total_budget)}</p>
                                </div>
                                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                                    <i class="fas fa-coins text-xl"></i>
                                </div>
                            </div>
                            <div class="mt-4 text-blue-100 text-sm">
                                <i class="fas fa-arrow-up ml-1"></i>
                                <span>۱۲% رشد نسبت به ماه قبل</span>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6 card-hover">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-green-100 text-sm">دوره‌های فعال</p>
                                    <p class="text-2xl font-bold mt-2">${data.active_periods}</p>
                                </div>
                                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                                    <i class="fas fa-calendar-check text-xl"></i>
                                </div>
                            </div>
                            <div class="mt-4 text-green-100 text-sm">
                                <span>${data.latest_period || 'بدون دوره'}</span>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl shadow-lg p-6 card-hover">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-purple-100 text-sm">تعداد حساب‌ها</p>
                                    <p class="text-2xl font-bold mt-2">${data.accounts_count}</p>
                                </div>
                                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                                    <i class="fas fa-book text-xl"></i>
                                </div>
                            </div>
                            <div class="mt-4 text-purple-100 text-sm">
                                <i class="fas fa-check-circle ml-1"></i>
                                <span>${data.budgetable_accounts} قابل بودجه‌ریزی</span>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-orange-500 to-orange-600 text-white rounded-xl shadow-lg p-6 card-hover">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-orange-100 text-sm">میانگین انحراف</p>
                                    <p class="text-2xl font-bold mt-2">${data.avg_variance || 0}%</p>
                                </div>
                                <div class="bg-white bg-opacity-20 p-3 rounded-lg">
                                    <i class="fas fa-chart-bar text-xl"></i>
                                </div>
                            </div>
                            <div class="mt-4 text-orange-100 text-sm">
                                ${data.avg_variance > 10 ? '<i class="fas fa-exclamation-triangle ml-1"></i><span>نیاز به بررسی</span>' : '<i class="fas fa-check ml-1"></i><span>در وضعیت مطلوب</span>'}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Charts and Recent Activity -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Budget Distribution Chart -->
                        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-gray-800">توزیع بودجه بر اساس دپارتمان</h3>
                                <select id="chart-period" class="border border-gray-300 rounded-lg px-3 py-1 text-sm">
                                    <option value="annual">سالانه</option>
                                    <option value="quarterly">فصلی</option>
                                    <option value="monthly">ماهانه</option>
                                </select>
                            </div>
                            <div class="h-64">
                                <canvas id="budgetDistributionChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-bold text-gray-800 mb-6">آخرین فعالیت‌ها</h3>
                            <div class="space-y-4">
                                ${renderRecentActivities(data.recent_activities || [])}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                        <h3 class="text-lg font-bold text-gray-800 mb-6">اقدامات سریع</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <button onclick="loadPage('manual-entry')" class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 text-center transition-all duration-200">
                                <i class="fas fa-edit text-blue-600 text-2xl mb-2"></i>
                                <p class="font-medium text-blue-800">ورود دستی بودجه</p>
                                <p class="text-blue-600 text-sm mt-1">ثبت مقادیر جدید</p>
                            </button>
                            
                            <button onclick="showCreatePeriodModal()" class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-lg p-4 text-center transition-all duration-200">
                                <i class="fas fa-plus-circle text-green-600 text-2xl mb-2"></i>
                                <p class="font-medium text-green-800">دوره جدید</p>
                                <p class="text-green-600 text-sm mt-1">ایجاد دوره بودجه</p>
                            </button>
                            
                            <button onclick="generateReport()" class="bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg p-4 text-center transition-all duration-200">
                                <i class="fas fa-file-export text-purple-600 text-2xl mb-2"></i>
                                <p class="font-medium text-purple-800">گزارش فوری</p>
                                <p class="text-purple-600 text-sm mt-1">صدور گزارش PDF</p>
                            </button>
                            
                            <button onclick="showImportModal()" class="bg-orange-50 hover:bg-orange-100 border border-orange-200 rounded-lg p-4 text-center transition-all duration-200">
                                <i class="fas fa-file-import text-orange-600 text-2xl mb-2"></i>
                                <p class="font-medium text-orange-800">ورود از Excel</p>
                                <p class="text-orange-600 text-sm mt-1">بارگذاری فایل</p>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Active Periods -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-800">دوره‌های بودجه فعال</h3>
                            <button onclick="loadPage('budget-periods')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                مشاهده همه
                                <i class="fas fa-arrow-left mr-1"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-right">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="p-3 text-sm font-medium text-gray-700">نام دوره</th>
                                        <th class="p-3 text-sm font-medium text-gray-700">نوع</th>
                                        <th class="p-3 text-sm font-medium text-gray-700">تاریخ شروع</th>
                                        <th class="p-3 text-sm font-medium text-gray-700">تاریخ پایان</th>
                                        <th class="p-3 text-sm font-medium text-gray-700">کل بودجه</th>
                                        <th class="p-3 text-sm font-medium text-gray-700">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${renderActivePeriods(data.active_periods_list || [])}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            // Initialize chart
            initializeBudgetChart();
        }
        
        // Render recent activities
        function renderRecentActivities(activities) {
            if (!activities || activities.length === 0) {
                return `
                    <div class="text-center py-8">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">فعلاً فعالیتی ثبت نشده است</p>
                    </div>
                `;
            }
            
            return activities.map(activity => `
                <div class="flex items-start border-b border-gray-100 pb-4 last:border-0">
                    <div class="ml-3">
                        <div class="w-10 h-10 rounded-full ${getActivityColor(activity.type)} flex items-center justify-center">
                            <i class="fas ${getActivityIcon(activity.type)} text-white"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-800">${activity.title}</p>
                        <p class="text-gray-600 text-sm mt-1">${activity.description}</p>
                        <p class="text-gray-400 text-xs mt-2">
                            <i class="far fa-clock ml-1"></i>
                            ${activity.time}
                        </p>
                    </div>
                </div>
            `).join('');
        }
        
        // Render active periods
        function renderActivePeriods(periods) {
            if (!periods || periods.length === 0) {
                return `
                    <tr>
                        <td colspan="6" class="p-8 text-center text-gray-500">
                            هیچ دوره فعالی یافت نشد
                        </td>
                    </tr>
                `;
            }
            
            return periods.map(period => `
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="p-3">
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-green-500 rounded-full ml-2"></div>
                            <span class="font-medium">${period.name}</span>
                        </div>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                            ${period.type === 'annual' ? 'سالانه' : period.type === 'quarterly' ? 'سهماهه' : 'ماهانه'}
                        </span>
                    </td>
                    <td class="p-3 text-gray-600">${period.start_date}</td>
                    <td class="p-3 text-gray-600">${period.end_date}</td>
                    <td class="p-3 font-bold text-gray-800">${formatCurrency(period.total_budget)}</td>
                    <td class="p-3">
                        <button onclick="viewPeriod(${period.id})" class="text-blue-600 hover:text-blue-800 ml-2">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editPeriod(${period.id})" class="text-green-600 hover:text-green-800 ml-2">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Initialize budget chart
        function initializeBudgetChart() {
            const ctx = document.getElementById('budgetDistributionChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['فروش', 'مالی', 'فناوری اطلاعات', 'منابع انسانی'],
                    datasets: [{
                        data: [35, 25, 20, 20],
                        backgroundColor: [
                            '#3b82f6', // Blue
                            '#10b981', // Green
                            '#8b5cf6', // Purple
                            '#f59e0b'  // Orange
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'left',
                            rtl: true,
                            labels: {
                                font: {
                                    family: 'Vazirmatn'
                                }
                            }
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.parsed}%`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Load budget periods
        async function loadBudgetPeriods() {
            showLoading('در حال بارگذاری دوره‌های بودجه...');
            
            try {
                const response = await fetch(`${API_URL}&action=get_periods`);
                const data = await response.json();
                
                if (data.success) {
                    renderBudgetPeriods(data.data);
                } else {
                    showError('خطا در بارگذاری دوره‌ها');
                }
            } catch (error) {
                console.error('Error loading periods:', error);
                showError('خطا در ارتباط با سرور');
            }
            
            hideLoading();
        }
        
        // Render budget periods
        function renderBudgetPeriods(periods) {
            const contentArea = document.getElementById('content-area');
            
            contentArea.innerHTML = `
                <div class="animate-fade-in">
                    <!-- Page Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">مدیریت دوره‌های بودجه</h3>
                            <p class="text-gray-600 mt-1">ایجاد، ویرایش و مدیریت دوره‌های بودجه‌ریزی</p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <button onclick="showCreatePeriodModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                                <i class="fas fa-plus ml-2"></i>
                                ایجاد دوره جدید
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت</label>
                                <select id="filter-status" class="w-full border border-gray-300 rounded-lg p-2">
                                    <option value="">همه</option>
                                    <option value="active">فعال</option>
                                    <option value="draft">پیش‌نویس</option>
                                    <option value="closed">بسته</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">نوع دوره</label>
                                <select id="filter-type" class="w-full border border-gray-300 rounded-lg p-2">
                                    <option value="">همه</option>
                                    <option value="annual">سالانه</option>
                                    <option value="quarterly">سهماهه</option>
                                    <option value="monthly">ماهانه</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">سال مالی</label>
                                <select id="filter-year" class="w-full border border-gray-300 rounded-lg p-2">
                                    <option value="">همه</option>
                                    <option value="1403">۱۴۰۳</option>
                                    <option value="1402">۱۴۰۲</option>
                                    <option value="1401">۱۴۰۱</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button onclick="filterPeriods()" class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg w-full">
                                    اعمال فیلتر
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Periods Table -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-right">
                                <thead>
                                    <tr class="bg-gray-800 text-white">
                                        <th class="p-4 text-sm font-medium">نام دوره</th>
                                        <th class="p-4 text-sm font-medium">نوع</th>
                                        <th class="p-4 text-sm font-medium">بازه زمانی</th>
                                        <th class="p-4 text-sm font-medium">وضعیت</th>
                                        <th class="p-4 text-sm font-medium">بودجه کل</th>
                                        <th class="p-4 text-sm font-medium">ایجاد کننده</th>
                                        <th class="p-4 text-sm font-medium">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${renderPeriodsTable(periods)}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                        <div class="bg-gradient-to-r from-blue-50 to-white border border-blue-100 rounded-xl p-4">
                            <div class="flex items-center">
                                <div class="bg-blue-100 p-3 rounded-lg ml-3">
                                    <i class="fas fa-calendar text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-gray-600 text-sm">کل دوره‌ها</p>
                                    <p class="text-2xl font-bold text-gray-800">${periods.length}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-green-50 to-white border border-green-100 rounded-xl p-4">
                            <div class="flex items-center">
                                <div class="bg-green-100 p-3 rounded-lg ml-3">
                                    <i class="fas fa-check-circle text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-gray-600 text-sm">دوره‌های فعال</p>
                                    <p class="text-2xl font-bold text-gray-800">${periods.filter(p => p.status === 'active').length}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-r from-purple-50 to-white border border-purple-100 rounded-xl p-4">
                            <div class="flex items-center">
                                <div class="bg-purple-100 p-3 rounded-lg ml-3">
                                    <i class="fas fa-coins text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="text-gray-600 text-sm">میانگین بودجه</p>
                                    <p class="text-2xl font-bold text-gray-800">${formatCurrency(calculateAverageBudget(periods))}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Render periods table
        function renderPeriodsTable(periods) {
            if (!periods || periods.length === 0) {
                return `
                    <tr>
                        <td colspan="7" class="p-8 text-center text-gray-500">
                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-3"></i>
                            <p class="text-lg">هیچ دوره بودجه‌ای یافت نشد</p>
                            <p class="text-gray-600 mt-2">برای شروع یک دوره جدید ایجاد کنید</p>
                            <button onclick="showCreatePeriodModal()" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                                <i class="fas fa-plus ml-2"></i>
                                ایجاد اولین دوره
                            </button>
                        </td>
                    </tr>
                `;
            }
            
            return periods.map(period => `
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="p-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full ${getStatusColor(period.status)} ml-2"></div>
                            <div>
                                <p class="font-medium text-gray-800">${period.name}</p>
                                <p class="text-gray-500 text-sm mt-1">نسخه: ${period.version}</p>
                            </div>
                        </div>
                    </td>
                    <td class="p-4">
                        <span class="px-3 py-1 rounded-full text-xs font-medium ${getTypeBadgeClass(period.type)}">
                            ${period.type === 'annual' ? 'سالانه' : period.type === 'quarterly' ? 'سهماهه' : 'ماهانه'}
                        </span>
                    </td>
                    <td class="p-4">
                        <p class="text-gray-700">${period.start_date}</p>
                        <p class="text-gray-500 text-sm">تا ${period.end_date}</p>
                    </td>
                    <td class="p-4">
                        <span class="px-3 py-1 rounded-full text-xs font-medium ${getStatusBadgeClass(period.status)}">
                            ${getStatusText(period.status)}
                        </span>
                    </td>
                    <td class="p-4 font-bold text-gray-800">${formatCurrency(period.total_budget || 0)}</td>
                    <td class="p-4 text-gray-600">${period.creator || 'سیستم'}</td>
                    <td class="p-4">
                        <div class="flex items-center space-x-2 space-x-reverse">
                            <button onclick="viewPeriod(${period.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="مشاهده">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="editPeriod(${period.id})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="ویرایش">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="copyPeriod(${period.id})" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg" title="کپی">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button onclick="deletePeriod(${period.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="حذف">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        // Load manual budget entry
        async function loadManualEntry() {
            showLoading('در حال بارگذاری فرم ورود بودجه...');
            
            try {
                // Load periods for dropdown
                const periodsResponse = await fetch(`${API_URL}&action=get_periods&status=active`);
                const periodsData = await periodsResponse.json();
                
                // Load accounts
                const accountsResponse = await fetch(`${API_URL}&action=get_accounts&budgetable=true`);
                const accountsData = await accountsResponse.json();
                
                // Load departments
                const deptsResponse = await fetch(`${API_URL}&action=get_departments`);
                const deptsData = await deptsResponse.json();
                
                renderManualEntryForm(
                    periodsData.data || [],
                    accountsData.data || [],
                    deptsData.data || []
                );
                
            } catch (error) {
                console.error('Error loading manual entry form:', error);
                showError('خطا در بارگذاری فرم');
            }
            
            hideLoading();
        }
        
        // Render manual entry form
        function renderManualEntryForm(periods, accounts, departments) {
            const contentArea = document.getElementById('content-area');
            
            contentArea.innerHTML = `
                <div class="animate-fade-in">
                    <!-- Page Header -->
                    <div class="mb-8">
                        <h3 class="text-2xl font-bold text-gray-800">ورود دستی بودجه</h3>
                        <p class="text-gray-600 mt-1">ثبت مقادیر بودجه به صورت دستی برای حساب‌ها</p>
                    </div>
                    
                    <!-- Form Container -->
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                        <form id="manualEntryForm" onsubmit="return submitManualEntry(event)">
                            <!-- Selection Row -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">دوره بودجه *</label>
                                    <select id="periodSelect" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">انتخاب کنید</option>
                                        ${periods.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">حساب *</label>
                                    <select id="accountSelect" required class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">انتخاب کنید</option>
                                        ${accounts.map(a => `<option value="${a.id}" data-type="${a.account_type}">${a.account_code} - ${a.account_name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">دپارتمان</label>
                                    <select id="departmentSelect" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">انتخاب نشده</option>
                                        ${departments.map(d => `<option value="${d.id}">${d.department_name}</option>`).join('')}
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">پروژه</label>
                                    <select id="projectSelect" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">انتخاب نشده</option>
                                        <option value="1">پروژه توسعه وبسایت</option>
                                        <option value="2">پروژه بازاریابی دیجیتال</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Monthly Amounts -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-semibold text-gray-800">
                                        <i class="fas fa-calendar-alt ml-2"></i>
                                        مقادیر ماهانه (افغانی)
                                    </h4>
                                    <div class="flex space-x-2 space-x-reverse">
                                        <button type="button" onclick="distributeEvenly()" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm">
                                            توزیع مساوی
                                        </button>
                                        <button type="button" onclick="applyTemplate()" class="px-3 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded-lg text-sm">
                                            اعمال الگو
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                    ${renderMonthInputs()}
                                </div>
                                
                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-gray-600 text-sm">جمع سالانه:</p>
                                            <p id="annual-total" class="text-xl font-bold text-blue-700">0 افغانی</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600 text-sm">میانگین ماهانه:</p>
                                            <p id="monthly-average" class="text-lg font-medium text-gray-800">0 افغانی</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notes -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت (اختیاری)</label>
                                <textarea id="notes" rows="3" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="توضیحات مربوط به این سطر بودجه..."></textarea>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="flex justify-end space-x-3 space-x-reverse">
                                <button type="button" onclick="resetForm()" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                    پاک کردن فرم
                                </button>
                                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center">
                                    <i class="fas fa-save ml-2"></i>
                                    ذخیره سطر بودجه
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Recent Entries -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h4 class="text-lg font-semibold text-gray-800">آخرین ورودی‌های شما</h4>
                            <button onclick="loadBudgetLines()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                مشاهده همه
                                <i class="fas fa-arrow-left mr-1"></i>
                            </button>
                        </div>
                        <div id="recent-entries">
                            <div class="text-center py-8">
                                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-600 mx-auto"></div>
                                <p class="text-gray-500 mt-4">در حال بارگذاری...</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Load recent entries
            loadRecentEntries();
            
            // Setup monthly amount calculations
            setupMonthlyCalculations();
        }
        
        // Render month inputs
        function renderMonthInputs() {
            const persianMonths = [
                { id: 'january', name: 'حمل', placeholder: 'مقدار حمل' },
                { id: 'february', name: 'ثور', placeholder: 'مقدار ثور' },
                { id: 'march', name: 'جوزا', placeholder: 'مقدار جوزا' },
                { id: 'april', name: 'سرطان', placeholder: 'مقدار سرطان' },
                { id: 'may', name: 'اسد', placeholder: 'مقدار اسد' },
                { id: 'june', name: 'سنبله', placeholder: 'مقدار سنبله' },
                { id: 'july', name: 'میزان', placeholder: 'مقدار میزان' },
                { id: 'august', name: 'عقرب', placeholder: 'مقدار عقرب' },
                { id: 'september', name: 'قوس', placeholder: 'مقدار قوس' },
                { id: 'october', name: 'جدی', placeholder: 'مقدار جدی' },
                { id: 'november', name: 'دلو', placeholder: 'مقدار دلو' },
                { id: 'december', name: 'حوت', placeholder: 'مقدار حوت' }
            ];
            
            return persianMonths.map(month => `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">${month.name}</label>
                    <input type="number" 
                           id="${month.id}" 
                           data-month="${month.name}"
                           class="month-amount w-full border border-gray-300 rounded-lg p-3 text-left focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                           placeholder="${month.placeholder}"
                           step="1000"
                           min="0"
                           oninput="calculateTotals()">
                    <div class="text-xs text-gray-500 mt-1 text-left">AFN</div>
                </div>
            `).join('');
        }
        
        // Setup monthly calculations
        function setupMonthlyCalculations() {
            document.querySelectorAll('.month-amount').forEach(input => {
                input.addEventListener('input', calculateTotals);
            });
        }
        
        // Calculate totals
        function calculateTotals() {
            let annualTotal = 0;
            let monthCount = 0;
            
            document.querySelectorAll('.month-amount').forEach(input => {
                const value = parseFloat(input.value) || 0;
                annualTotal += value;
                if (value > 0) monthCount++;
            });
            
            const monthlyAverage = monthCount > 0 ? annualTotal / 12 : 0;
            
            document.getElementById('annual-total').textContent = formatCurrency(annualTotal);
            document.getElementById('monthly-average').textContent = formatCurrency(monthlyAverage);
        }
        
        // Distribute evenly
        function distributeEvenly() {
            const totalInput = document.getElementById('annual-total-input');
            if (!totalInput) {
                showConfirmModal('توزیع مساوی', 'می‌خواهید چه مقدار به صورت مساوی توزیع شود؟', (amount) => {
                    if (amount) {
                        const monthlyAmount = amount / 12;
                        document.querySelectorAll('.month-amount').forEach(input => {
                            input.value = Math.round(monthlyAmount);
                        });
                        calculateTotals();
                    }
                }, true);
            }
        }
        
        // Submit manual entry
        async function submitManualEntry(event) {
            event.preventDefault();
            
            const periodId = document.getElementById('periodSelect').value;
            const accountId = document.getElementById('accountSelect').value;
            const departmentId = document.getElementById('departmentSelect').value;
            const projectId = document.getElementById('projectSelect').value;
            const notes = document.getElementById('notes').value;
            
            // Collect monthly amounts
            const monthlyAmounts = {};
            document.querySelectorAll('.month-amount').forEach(input => {
                monthlyAmounts[input.id] = parseFloat(input.value) || 0;
            });
            
            const data = {
                period_id: periodId,
                account_id: accountId,
                department_id: departmentId || null,
                project_id: projectId || null,
                monthly_amounts: monthlyAmounts,
                notes: notes,
                user_id: currentUser.id
            };
            
            showLoading('در حال ذخیره‌سازی...');
            
            try {
                const response = await fetch(`${API_URL}&action=add_budget_line`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('سطر بودجه با موفقیت ذخیره شد');
                    resetForm();
                    loadRecentEntries();
                } else {
                    showError(result.message || 'خطا در ذخیره‌سازی');
                }
            } catch (error) {
                console.error('Error saving budget line:', error);
                showError('خطا در ارتباط با سرور');
            }
            
            hideLoading();
        }
        
        // Reset form
        function resetForm() {
            document.getElementById('manualEntryForm').reset();
            document.querySelectorAll('.month-amount').forEach(input => {
                input.value = '';
            });
            calculateTotals();
        }
        
        // Load recent entries
        async function loadRecentEntries() {
            try {
                const response = await fetch(`${API_URL}&action=get_recent_entries&user_id=${currentUser.id}&limit=5`);
                const data = await response.json();
                
                const container = document.getElementById('recent-entries');
                if (data.success && data.data.length > 0) {
                    container.innerHTML = renderRecentEntries(data.data);
                } else {
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">هیچ ورودی‌ای ثبت نکرده‌اید</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading recent entries:', error);
            }
        }
        
        // Show create period modal
        function showCreatePeriodModal() {
            const modalHTML = `
                <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl">
                        <div class="flex justify-between items-center p-6 border-b">
                            <h3 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-plus-circle ml-2 text-blue-600"></i>
                                ایجاد دوره بودجه جدید
                            </h3>
                            <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <form id="createPeriodForm" onsubmit="return createBudgetPeriod(event)">
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-gray-700 mb-2">نام دوره *</label>
                                        <input type="text" name="period_name" required 
                                               class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                               placeholder="مثال: سال مالی ۱۴۰۳">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 mb-2">نوع دوره *</label>
                                        <select name="period_type" required class="w-full p-3 border border-gray-300 rounded-lg">
                                            <option value="annual">سالانه</option>
                                            <option value="quarterly">سهماهه</option>
                                            <option value="monthly">ماهانه</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 mb-2">تاریخ شروع *</label>
                                        <input type="date" name="start_date" required 
                                               class="w-full p-3 border border-gray-300 rounded-lg">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-gray-700 mb-2">تاریخ پایان *</label>
                                        <input type="date" name="end_date" required 
                                               class="w-full p-3 border border-gray-300 rounded-lg">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-gray-700 mb-2">توضیحات</label>
                                        <textarea name="description" rows="3" 
                                                  class="w-full p-3 border border-gray-300 rounded-lg"
                                                  placeholder="توضیحات مربوط به این دوره بودجه..."></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end gap-3 p-6 border-t">
                                <button type="button" onclick="closeModal()" 
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                    لغو
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center">
                                    <i class="fas fa-check ml-2"></i>
                                    ایجاد دوره
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.getElementById('modals-container').innerHTML = modalHTML;
        }
        
        // Create budget period
        async function createBudgetPeriod(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const data = {
                period_name: formData.get('period_name'),
                period_type: formData.get('period_type'),
                start_date: formData.get('start_date'),
                end_date: formData.get('end_date'),
                description: formData.get('description'),
                user_id: currentUser.id
            };
            
            showLoading('در حال ایجاد دوره...');
            
            try {
                const response = await fetch(`${API_URL}&action=create_period`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('دوره بودجه با موفقیت ایجاد شد');
                    closeModal();
                    loadBudgetPeriods();
                } else {
                    showError(result.message || 'خطا در ایجاد دوره');
                }
            } catch (error) {
                console.error('Error creating period:', error);
                showError('خطا در ارتباط با سرور');
            }
            
            hideLoading();
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('modals-container').innerHTML = '';
        }
        
        // Show success notification
        function showSuccess(message) {
            showNotification(message, 'success');
        }
        
        // Show error notification
        function showError(message) {
            showNotification(message, 'error');
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const notification = document.createElement('div');
            notification.className = `${colors[type]} text-white p-4 rounded-lg shadow-lg animate-fade-in flex items-center`;
            notification.innerHTML = `
                <i class="fas ${icons[type]} ml-3"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" class="mr-auto text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            const container = document.getElementById('notification-container');
            container.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Show loading
        function showLoading(message = 'لطفا صبر کنید...') {
            const loading = document.getElementById('loading');
            document.getElementById('loading-message').textContent = message;
            loading.classList.remove('hidden');
        }
        
        // Hide loading
        function hideLoading() {
            const loading = document.getElementById('loading');
            loading.classList.add('hidden');
        }
        
        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fa-AF').format(amount) + ' افغانی';
        }
        
        // Helper functions for status/type badges
        function getStatusColor(status) {
            switch(status) {
                case 'active': return 'bg-green-500';
                case 'draft': return 'bg-yellow-500';
                case 'closed': return 'bg-gray-500';
                default: return 'bg-gray-300';
            }
        }
        
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'active': return 'bg-green-100 text-green-800';
                case 'draft': return 'bg-yellow-100 text-yellow-800';
                case 'closed': return 'bg-gray-100 text-gray-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }
        
        function getTypeBadgeClass(type) {
            switch(type) {
                case 'annual': return 'bg-blue-100 text-blue-800';
                case 'quarterly': return 'bg-purple-100 text-purple-800';
                case 'monthly': return 'bg-green-100 text-green-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }
        
        function getStatusText(status) {
            switch(status) {
                case 'active': return 'فعال';
                case 'draft': return 'پیش‌نویس';
                case 'closed': return 'بسته';
                default: return status;
            }
        }
        
        function getActivityColor(type) {
            switch(type) {
                case 'create': return 'bg-green-500';
                case 'update': return 'bg-blue-500';
                case 'delete': return 'bg-red-500';
                default: return 'bg-gray-500';
            }
        }
        
        function getActivityIcon(type) {
            switch(type) {
                case 'create': return 'fa-plus';
                case 'update': return 'fa-edit';
                case 'delete': return 'fa-trash';
                default: return 'fa-info';
            }
        }
        
        function calculateAverageBudget(periods) {
            if (!periods.length) return 0;
            const total = periods.reduce((sum, period) => sum + (period.total_budget || 0), 0);
            return Math.round(total / periods.length);
        }
        
        // Other page loading functions (simplified for this example)
        function loadBudgetAnalysis() {
            document.getElementById('content-area').innerHTML = `
                <div class="animate-fade-in">
                    <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                        <i class="fas fa-chart-line text-6xl text-blue-500 mb-4"></i>
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">تحلیل بودجه</h3>
                        <p class="text-gray-600 mb-6">این بخش در حال توسعه می‌باشد. به زودی قابلیت‌های تحلیل پیشرفته اضافه خواهد شد.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <i class="fas fa-chart-pie text-3xl text-blue-600 mb-2"></i>
                                <p class="font-medium">تحلیل ماهانه</p>
                            </div>
                            <div class="p-4 bg-green-50 rounded-lg">
                                <i class="fas fa-balance-scale text-3xl text-green-600 mb-2"></i>
                                <p class="font-medium">مقایسه دوره‌ای</p>
                            </div>
                            <div class="p-4 bg-purple-50 rounded-lg">
                                <i class="fas fa-tachometer-alt text-3xl text-purple-600 mb-2"></i>
                                <p class="font-medium">پیش‌بینی مصرف</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function loadReports() {
            document.getElementById('content-area').innerHTML = `
                <div class="animate-fade-in">
                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6">گزارشات سیستم</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="border border-gray-200 rounded-lg p-6 hover:border-blue-300 transition-colors">
                                <div class="flex items-center mb-4">
                                    <div class="bg-blue-100 p-3 rounded-lg ml-3">
                                        <i class="fas fa-file-pdf text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800">گزارش مفصل بودجه</h4>
                                        <p class="text-gray-600 text-sm">خلاصه کامل دوره</p>
                                    </div>
                                </div>
                                <button onclick="generateReport('detailed')" class="w-full bg-blue-50 hover:bg-blue-100 text-blue-700 py-2 rounded-lg">
                                    ایجاد گزارش
                                </button>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-6 hover:border-green-300 transition-colors">
                                <div class="flex items-center mb-4">
                                    <div class="bg-green-100 p-3 rounded-lg ml-3">
                                        <i class="fas fa-file-excel text-green-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800">خروجی Excel</h4>
                                        <p class="text-gray-600 text-sm">داده‌های خام</p>
                                    </div>
                                </div>
                                <button onclick="exportToExcel()" class="w-full bg-green-50 hover:bg-green-100 text-green-700 py-2 rounded-lg">
                                    دانلود Excel
                                </button>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-6 hover:border-purple-300 transition-colors">
                                <div class="flex items-center mb-4">
                                    <div class="bg-purple-100 p-3 rounded-lg ml-3">
                                        <i class="fas fa-chart-bar text-purple-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-gray-800">گزارش نموداری</h4>
                                        <p class="text-gray-600 text-sm">تحلیل گرافیکی</p>
                                    </div>
                                </div>
                                <button onclick="generateChartReport()" class="w-full bg-purple-50 hover:bg-purple-100 text-purple-700 py-2 rounded-lg">
                                    ایجاد نمودار
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Check database connection
        async function checkDatabaseConnection() {
            try {
                const response = await fetch(`${API_URL}&action=check_connection`);
                const data = await response.json();
                
                if (!data.connected) {
                    showError('خطا در اتصال به دیتابیس');
                }
            } catch (error) {
                console.error('Connection check failed:', error);
            }
        }
        
        // Load system stats for header
        async function loadSystemStats() {
            try {
                const response = await fetch(`${API_URL}&action=get_header_stats`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('header-total-budget').textContent = formatCurrency(data.total_budget);
                    document.getElementById('header-active-period').textContent = data.active_period || 'بدون دوره';
                }
            } catch (error) {
                console.error('Error loading header stats:', error);
            }
        }
        
        // Export to Excel
        async function exportToExcel() {
            showLoading('در حال آماده‌سازی فایل Excel...');
            
            try {
                const response = await fetch(`${API_URL}&action=export_excel`);
                const blob = await response.blob();
                
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `budget_export_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showSuccess('فایل Excel با موفقیت دانلود شد');
            } catch (error) {
                console.error('Error exporting to Excel:', error);
                showError('خطا در ایجاد فایل Excel');
            }
            
            hideLoading();
        }
        
        // Logout function
        function logout() {
            showLoading('در حال خروج از سیستم...');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 1000);
        }
        
        // Placeholder functions for other actions
        function viewPeriod(id) {
            showNotification(`مشاهده دوره ${id} (در حال توسعه)`);
        }
        
        function editPeriod(id) {
            showNotification(`ویرایش دوره ${id} (در حال توسعه)`);
        }
        
        function copyPeriod(id) {
            showNotification(`کپی دوره ${id} (در حال توسعه)`);
        }
        
        function deletePeriod(id) {
            showConfirmModal('حذف دوره بودجه', 'آیا از حذف این دوره مطمئن هستید؟ این عمل قابل بازگشت نیست.', () => {
                showNotification('دوره با موفقیت حذف شد (نمایشی)');
            });
        }
        
        function filterPeriods() {
            showNotification('اعمال فیلتر (در حال توسعه)');
        }
        
        function showConfirmModal(title, message, onConfirm, withInput = false) {
            let inputHtml = '';
            if (withInput) {
                inputHtml = `
                    <div class="mt-4">
                        <label class="block text-gray-700 mb-2">مبلغ کل (افغانی)</label>
                        <input type="number" id="confirm-input" class="w-full p-3 border border-gray-300 rounded-lg" placeholder="مبلغ را وارد کنید">
                    </div>
                `;
            }
            
            const modalHTML = `
                <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                        <div class="p-6 border-b">
                            <h3 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-exclamation-triangle ml-2 text-yellow-500"></i>
                                ${title}
                            </h3>
                        </div>
                        
                        <div class="p-6">
                            <p class="text-gray-700">${message}</p>
                            ${inputHtml}
                        </div>
                        
                        <div class="flex justify-end gap-3 p-6 border-t">
                            <button onclick="closeModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                لغو
                            </button>
                            <button onclick="handleConfirm(${withInput})" 
                                    class="px-4 py-2 ${withInput ? 'bg-blue-600 hover:bg-blue-700' : 'bg-red-600 hover:bg-red-700'} text-white rounded-lg">
                                تایید
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('modals-container').innerHTML = modalHTML;
            
            window.handleConfirm = function(withInput) {
                let value = null;
                if (withInput) {
                    const input = document.getElementById('confirm-input');
                    value = input.value;
                    if (!value || parseFloat(value) <= 0) {
                        showError('لطفا مبلغ معتبر وارد کنید');
                        return;
                    }
                }
                closeModal();
                onConfirm(value);
            };
        }
        
        function showNotifications() {
            const modalHTML = `
                <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                        <div class="flex justify-between items-center p-6 border-b">
                            <h3 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-bell ml-2 text-blue-600"></i>
                                اعلان‌ها
                            </h3>
                            <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div class="p-6 max-h-96 overflow-y-auto">
                            <div class="space-y-4">
                                <div class="flex items-start p-3 bg-blue-50 rounded-lg">
                                    <div class="bg-blue-500 text-white p-2 rounded-lg ml-3">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium">بودجه فصل اول تکمیل شد</p>
                                        <p class="text-gray-600 text-sm mt-1">دوره سهماهه اول ۱۴۰۳ با موفقیت تایید شد.</p>
                                        <p class="text-gray-400 text-xs mt-2">۲ ساعت پیش</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start p-3 bg-green-50 rounded-lg">
                                    <div class="bg-green-500 text-white p-2 rounded-lg ml-3">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium">گزارش ماهانه آماده است</p>
                                        <p class="text-gray-600 text-sm mt-1">گزارش تحلیل بودجه اسفند ۱۴۰۲ تولید شد.</p>
                                        <p class="text-gray-400 text-xs mt-2">۱ روز پیش</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-start p-3 bg-yellow-50 rounded-lg">
                                    <div class="bg-yellow-500 text-white p-2 rounded-lg ml-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium">هشدار: انحراف بودجه</p>
                                        <p class="text-gray-600 text-sm mt-1">۳ حساب انحراف بیش از ۲۰% دارند.</p>
                                        <p class="text-gray-400 text-xs mt-2">۲ روز پیش</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6 border-t">
                            <button onclick="markAllAsRead()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 rounded-lg">
                                علامت‌گذاری همه به عنوان خوانده شده
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('modals-container').innerHTML = modalHTML;
        }
        
        function markAllAsRead() {
            showSuccess('همه اعلان‌ها خوانده شدند');
            closeModal();
        }
        
        // Initialize with dashboard
        loadPage('dashboard');
    </script>
</body>
</html>