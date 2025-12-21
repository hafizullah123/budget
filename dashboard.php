<?php
/* ================= DATABASE CONNECTION ================= */
$conn = new mysqli("localhost","root","","budget1");
if($conn->connect_error){
    die("Connection failed: ".$conn->connect_error);
}

/* ================= FETCH DASHBOARD STATISTICS ================= */
// 1. Total Original Budget (allocated budget)
$total_budget = $conn->query("
    SELECT SUM(budget + actual) as total_original_budget 
    FROM budget_details
")->fetch_assoc()['total_original_budget'] ?? 0;

// 2. Total Spent (actual)
$total_spent = $conn->query("
    SELECT SUM(actual) as total_spent 
    FROM budget_details
")->fetch_assoc()['total_spent'] ?? 0;

// 3. Total Remaining Budget
$total_remaining = $conn->query("
    SELECT SUM(budget) as total_remaining 
    FROM budget_details
")->fetch_assoc()['total_remaining'] ?? 0;

// 4. Overall Spending Percentage
$overall_percentage = $total_budget > 0 ? 
    round(($total_spent / $total_budget) * 100, 2) : 0;

// 5. Total Number of Budget Items
$total_items = $conn->query("
    SELECT COUNT(*) as total_items 
    FROM budget_details
")->fetch_assoc()['total_items'] ?? 0;

// 6. Total Number of Expense Vouchers
$total_vouchers = $conn->query("
    SELECT COUNT(*) as total_vouchers 
    FROM expense_vouchers
")->fetch_assoc()['total_vouchers'] ?? 0;

// 7. Recent Expenses (last 5)
$recent_expenses = $conn->query("
    SELECT ev.voucher_number, ev.expense_type, ev.voucher_date, ev.total_debit,
           evi.details, evi.general_code
    FROM expense_vouchers ev
    LEFT JOIN expense_voucher_items evi ON ev.id = evi.voucher_id
    ORDER BY ev.voucher_date DESC, ev.id DESC
    LIMIT 5
");

// 8. Budget Categories Summary
$budget_summary = $conn->query("
    SELECT 
        sub_code as category,
        SUM(budget + actual) as original_budget,
        SUM(actual) as spent,
        SUM(budget) as remaining,
        CASE 
            WHEN SUM(budget + actual) > 0 THEN 
                ROUND((SUM(actual) / SUM(budget + actual)) * 100, 2)
            ELSE 0 
        END as percentage
    FROM budget_details
    GROUP BY sub_code
    ORDER BY spent DESC
    LIMIT 8
");
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد سیستم بودجه</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --sidebar-bg: #1a252f;
            --sidebar-hover: #2c3e50;
            --sidebar-text: #bdc3c7;
            --sidebar-active: #3498db;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f6fa;
            color: #333;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            right: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .system-name h2 {
            color: white;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .system-name span {
            font-size: 12px;
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 0;
            height: calc(100vh - 140px);
            overflow-y: auto;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-right: 3px solid transparent;
        }

        .menu-item:hover {
            background: var(--sidebar-hover);
            color: white;
            border-right-color: var(--sidebar-active);
        }

        .menu-item.active {
            background: var(--sidebar-hover);
            color: white;
            border-right-color: var(--sidebar-active);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .menu-text {
            flex: 1;
        }

        .menu-badge {
            background: var(--sidebar-active);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .menu-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 15px 20px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .user-details h4 {
            color: white;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .user-details span {
            font-size: 12px;
            opacity: 0.7;
        }

        /* Main Content Styles */
        .main-content {
            margin-right: 260px;
            padding: 20px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 {
            color: var(--primary);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title h1 i {
            color: var(--secondary);
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-time {
            background: var(--light);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
        }

        .notification-btn {
            background: var(--light);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            text-decoration: none;
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            left: -5px;
            background: var(--danger);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-top: 4px solid;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card.budget { border-color: var(--secondary); }
        .stat-card.spent { border-color: var(--danger); }
        .stat-card.remaining { border-color: var(--success); }
        .stat-card.percentage { border-color: var(--warning); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.budget .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.spent .stat-icon { background: linear-gradient(135deg, #f093fb, #f5576c); }
        .stat-card.remaining .stat-icon { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .stat-card.percentage .stat-icon { background: linear-gradient(135deg, #43e97b, #38f9d7); }

        .stat-menu {
            color: #999;
            cursor: pointer;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .stat-change {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--danger); }

        /* Dashboard Content */
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        @media (max-width: 1200px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }

        .chart-container, .recent-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            color: var(--primary);
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title i {
            color: var(--secondary);
        }

        .view-all {
            font-size: 14px;
            color: var(--secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Progress Bars */
        .progress-list {
            margin-top: 20px;
        }

        .progress-item {
            margin-bottom: 20px;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .progress-name {
            color: var(--dark);
            font-weight: 500;
        }

        .progress-percent {
            color: var(--primary);
            font-weight: bold;
        }

        .progress-bar {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, var(--secondary), #764ba2);
            position: relative;
            transition: width 1s ease-in-out;
        }

        /* Recent Activities */
        .recent-list {
            list-style: none;
        }

        .recent-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .recent-item:hover {
            background: #f8f9fa;
            border-radius: 8px;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .recent-content {
            flex: 1;
        }

        .recent-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .recent-meta {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: #666;
        }

        .recent-amount {
            font-weight: bold;
            color: var(--danger);
        }

        /* Budget Summary */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .summary-table th {
            background: #f8f9fa;
            color: var(--dark);
            font-weight: 600;
            padding: 12px 15px;
            text-align: right;
            border-bottom: 2px solid #eee;
        }

        .summary-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: right;
        }

        .summary-table tr:hover {
            background: #f8f9fa;
        }

        .percentage-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            min-width: 60px;
            text-align: center;
        }

        .percentage-badge.high { background: var(--danger); }
        .percentage-badge.medium { background: var(--warning); }
        .percentage-badge.low { background: var(--success); }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .quick-stat {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .quick-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .quick-stat-label {
            font-size: 12px;
            color: #666;
        }

        /* Footer */
        .main-footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eee;
            margin-top: 20px;
        }

        /* Toggle Sidebar Button for Mobile */
        .sidebar-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-right: 0;
                padding: 15px;
            }
            
            .sidebar-toggle {
                display: flex;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .top-bar-actions {
                width: 100%;
                justify-content: center;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-coins"></i>
            </div>
            <div class="system-name">
                <h2>سیستم بودجه</h2>
                <span>مدیریت مالی</span>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-home"></i>
                <span class="menu-text">داشبورد</span>
            </a>
            
            <div class="menu-divider"></div>
            
            <a href="expense_voucher.php" class="menu-item">
                <i class="fas fa-file-invoice-dollar"></i>
                <span class="menu-text">ثبت سند مصرف</span>
            </a>
            
            <a href="save_budget.php" class="menu-item">
                <i class="fas fa-coins"></i>
                <span class="menu-text">ثبت بودجه</span>
            </a>
            
            <a href="list_vouchers.php" class="menu-item">
                <i class="fas fa-list-alt"></i>
                <span class="menu-text">لیست سندها</span>
                <span class="menu-badge"><?php echo $total_vouchers; ?></span>
            </a>
            
            <a href="budget_report.php" class="menu-item">
                <i class="fas fa-chart-pie"></i>
                <span class="menu-text">گزارش بودجه</span>
            </a>
            
            <div class="menu-divider"></div>
            
            <a href="budget_details.php" class="menu-item">
                <i class="fas fa-table"></i>
                <span class="menu-text">جزییات بودجه</span>
                <span class="menu-badge"><?php echo $total_items; ?></span>
            </a>
            
            <a href="expense_types.php" class="menu-item">
                <i class="fas fa-tags"></i>
                <span class="menu-text">انواع مصرف</span>
            </a>
            
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">تنظیمات</span>
            </a>
            
            <div class="menu-divider"></div>
            
            <a href="help.php" class="menu-item">
                <i class="fas fa-question-circle"></i>
                <span class="menu-text">راهنما</span>
            </a>
            
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">خروج</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                
            </div>
           
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar fade-in">
            <div class="page-title">
                <h1>
                    <i class="fas fa-chart-line"></i>
                    داشبورد سیستم مدیریت بودجه
                </h1>
            </div>
            
            <div class="top-bar-actions">
                <div class="date-time">
                    <i class="fas fa-calendar-alt"></i>
                    <?php 
                        date_default_timezone_set('Asia/Kabul');
                        echo date('Y/m/d - H:i');
                    ?>
                </div>
                
                <a href="#" class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <!-- Total Budget -->
            <div class="stat-card budget">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-menu">
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo number_format($total_budget, 0); ?> <small>افغانی</small>
                </div>
                <div class="stat-label">کل بودجه تخصیص یافته</div>
                <div class="stat-footer">
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <?php echo $total_items; ?> آیتم
                    </div>
                    <div style="font-size: 12px; color: #999;">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Total Spent -->
            <div class="stat-card spent">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-menu">
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo number_format($total_spent, 0); ?> <small>افغانی</small>
                </div>
                <div class="stat-label">کل هزینه شده</div>
                <div class="stat-footer">
                    <div class="stat-change negative">
                        <i class="fas fa-arrow-up"></i>
                        <?php echo $total_vouchers; ?> سند
                    </div>
                    <div style="font-size: 12px; color: #999;">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Total Remaining -->
            <div class="stat-card remaining">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <div class="stat-menu">
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo number_format($total_remaining, 0); ?> <small>افغانی</small>
                </div>
                <div class="stat-label">کل بودجه باقیمانده</div>
                <div class="stat-footer">
                    <div class="stat-change positive">
                        <i class="fas fa-percentage"></i>
                        <?php echo $total_budget > 0 ? round(($total_remaining / $total_budget) * 100, 2) : 0; ?>%
                    </div>
                    <div style="font-size: 12px; color: #999;">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Overall Percentage -->
            <div class="stat-card percentage">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-menu">
                        <i class="fas fa-ellipsis-h"></i>
                    </div>
                </div>
                <div class="stat-value">
                    <?php echo $overall_percentage; ?>%
                </div>
                <div class="stat-label">درصد کلی مصرف</div>
                <div class="stat-footer">
                    <div class="stat-change <?php echo $overall_percentage > 70 ? 'negative' : ($overall_percentage > 40 ? 'warning' : 'positive'); ?>">
                        <i class="fas fa-<?php echo $overall_percentage > 70 ? 'exclamation-triangle' : ($overall_percentage > 40 ? 'minus' : 'check-circle'); ?>"></i>
                        <?php 
                            if ($overall_percentage > 70) {
                                echo 'مصرف بالا';
                            } elseif ($overall_percentage > 40) {
                                echo 'مصرف متوسط';
                            } else {
                                echo 'مصرف پایین';
                            }
                        ?>
                    </div>
                    <div style="font-size: 12px; color: #999;">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="dashboard-content fade-in">
            <!-- Left Column: Progress and Summary -->
            <div class="chart-container">
                <div class="section-title">
                    <span>
                        <i class="fas fa-tasks"></i>
                        پیشرفت مصرف بودجه
                    </span>
                    <a href="budget_report.php" class="view-all">
                        مشاهده همه
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                
                <div class="progress-list">
                    <?php 
                    // Reset pointer for budget summary
                    $budget_summary->data_seek(0);
                    while($row = $budget_summary->fetch_assoc()): 
                        $percentage = $row['percentage'];
                        $status_class = $percentage > 70 ? 'high' : ($percentage > 40 ? 'medium' : 'low');
                    ?>
                    <div class="progress-item">
                        <div class="progress-info">
                            <span class="progress-name"><?php echo htmlspecialchars($row['category']); ?></span>
                            <span class="progress-percent"><?php echo $percentage; ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min($percentage, 100); ?>%"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 12px; color: #666;">
                            <span>مصرف: <?php echo number_format($row['spent'], 0); ?> AFN</span>
                            <span>باقی: <?php echo number_format($row['remaining'], 0); ?> AFN</span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <!-- Budget Summary Table -->
                <div style="margin-top: 30px;">
                    <div class="section-title">
                        <span>
                            <i class="fas fa-table"></i>
                            خلاصه بودجه
                        </span>
                    </div>
                    
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>دسته‌بندی</th>
                                <th>بودجه</th>
                                <th>مصرف</th>
                                <th>باقی</th>
                                <th>درصد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $budget_summary->data_seek(0);
                            while($row = $budget_summary->fetch_assoc()): 
                                $percentage = $row['percentage'];
                                $status_class = $percentage > 70 ? 'high' : ($percentage > 40 ? 'medium' : 'low');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo number_format($row['original_budget'], 0); ?></td>
                                <td><?php echo number_format($row['spent'], 0); ?></td>
                                <td><?php echo number_format($row['remaining'], 0); ?></td>
                                <td>
                                    <span class="percentage-badge <?php echo $status_class; ?>">
                                        <?php echo $percentage; ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column: Recent Activities -->
            <div class="recent-container">
                <div class="section-title">
                    <span>
                        <i class="fas fa-history"></i>
                        آخرین فعالیت‌ها
                    </span>
                    <a href="list_vouchers.php" class="view-all">
                        همه
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                
                <ul class="recent-list">
                    <?php if($recent_expenses->num_rows > 0): ?>
                        <?php while($row = $recent_expenses->fetch_assoc()): ?>
                        <li class="recent-item">
                            <div class="recent-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="recent-content">
                                <div class="recent-title">
                                    <?php echo htmlspecialchars($row['details'] ?? $row['expense_type']); ?>
                                </div>
                                <div class="recent-meta">
                                    <span>#<?php echo htmlspecialchars($row['voucher_number']); ?></span>
                                    <span><?php echo htmlspecialchars($row['general_code']); ?></span>
                                    <span class="recent-amount"><?php echo number_format($row['total_debit'], 0); ?> AFN</span>
                                </div>
                                <div style="font-size: 11px; color: #999; margin-top: 3px;">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo htmlspecialchars($row['voucher_date']); ?>
                                </div>
                            </div>
                        </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="recent-item" style="text-align: center; color: #666; padding: 20px;">
                            <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                            هیچ فعالیتی ثبت نشده است
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Quick Stats -->
                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="quick-stat-value"><?php echo $total_items; ?></div>
                        <div class="quick-stat-label">آیتم بودجه</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-value"><?php echo $total_vouchers; ?></div>
                        <div class="quick-stat-label">سند مصرف</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-value">
                            <?php 
                                $total_codes = $conn->query("SELECT COUNT(DISTINCT code) as total FROM budget_details")->fetch_assoc()['total'];
                                echo $total_codes;
                            ?>
                        </div>
                        <div class="quick-stat-label">کد بودجه</div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-value"><?php echo date('m/Y'); ?></div>
                        <div class="quick-stat-label">ماه جاری</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="main-footer fade-in">
            <p>
                سیستم مدیریت بودجه | نسخه ۱.۰ 
            </p>
            <p style="margin-top: 10px; opacity: 0.7; font-size: 12px;">جوړي شوي د انجینر  حفیظ الله جهادوال لخوا 
            </p>
        </div>
    </div>

    <!-- Mobile Toggle Button -->
    <div class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </div>

    <script>
        // Toggle Sidebar for Mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            this.innerHTML = document.querySelector('.sidebar').classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 992 && 
                !sidebar.contains(event.target) && 
                !toggleBtn.contains(event.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });

        // Update time every minute
        function updateTime() {
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            };
            const formatter = new Intl.DateTimeFormat('fa-IR', options);
            const dateTimeElement = document.querySelector('.date-time');
            if (dateTimeElement) {
                dateTimeElement.innerHTML = `
                    <i class="fas fa-calendar-alt"></i>
                    ${formatter.format(now).replace('،', ' -')}
                `;
            }
        }

        // Update time immediately and then every minute
        updateTime();
        setInterval(updateTime, 60000);

        // Animate progress bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });

        // Add active class to menu items based on current page
        const currentPage = window.location.pathname.split('/').pop();
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>