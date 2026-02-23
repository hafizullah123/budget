<?php
/* ================= DATABASE CONNECTION ================= */
$conn = new mysqli("localhost","root","","budget1");
if($conn->connect_error){
    die("Connection failed: ".$conn->connect_error);
}

// Get date filter parameters (if any)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Set default to current month if invalid dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Prepare date filter for SQL
$date_filter = " AND ev.voucher_date BETWEEN '$start_date' AND '$end_date'";

/* ================= FETCH DASHBOARD STATISTICS ================= */
// 1. Total Original Budget (allocated budget)
$total_budget = $conn->query("
    SELECT SUM(budget + actual) as total_original_budget 
    FROM budget_details
")->fetch_assoc()['total_original_budget'] ?? 0;

// 2. Total Spent (actual) - With date filter
$total_spent_result = $conn->query("
    SELECT SUM(ev.total_debit) as total_spent 
    FROM expense_vouchers ev
    WHERE 1=1 $date_filter
");
$total_spent = $total_spent_result->fetch_assoc()['total_spent'] ?? 0;

// 3. Total Remaining Budget
$total_remaining = $total_budget - $total_spent;

// 4. Overall Spending Percentage
$overall_percentage = $total_budget > 0 ? 
    round(($total_spent / $total_budget) * 100, 2) : 0;

// 5. Total Number of Budget Items
$total_items = $conn->query("
    SELECT COUNT(*) as total_items 
    FROM budget_details
")->fetch_assoc()['total_items'] ?? 0;

// 6. Total Number of Expense Vouchers (with date filter)
$total_vouchers_result = $conn->query("
    SELECT COUNT(*) as total_vouchers 
    FROM expense_vouchers ev
    WHERE 1=1 $date_filter
");
$total_vouchers = $total_vouchers_result->fetch_assoc()['total_vouchers'] ?? 0;

// 7. Recent Expenses (last 5) - With date filter
// Note: the column in expense_vouchers is now `expense_type_desc`. Alias it as
// `expense_type` so the rest of the template can remain unchanged.
$recent_expenses = $conn->query(
    "SELECT ev.voucher_number,
           ev.expense_type_desc AS expense_type,
           ev.voucher_date,
           ev.total_debit,
           evi.details,
           evi.general_code
    FROM expense_vouchers ev
    LEFT JOIN expense_voucher_items evi ON ev.id = evi.voucher_id
    WHERE 1=1 $date_filter
    ORDER BY ev.voucher_date DESC, ev.id DESC
    LIMIT 5
");

// 8. Budget Categories Summary - With date filter for spent amount
$budget_summary = $conn->query("
    SELECT 
        bd.sub_code as category,
        SUM(bd.budget + bd.actual) as original_budget,
        COALESCE(SUM(evi.debit), 0) as spent,
        (SUM(bd.budget + bd.actual) - COALESCE(SUM(evi.debit), 0)) as remaining,
        CASE 
            WHEN SUM(bd.budget + bd.actual) > 0 THEN 
                ROUND((COALESCE(SUM(evi.debit), 0) / SUM(bd.budget + bd.actual)) * 100, 2)
            ELSE 0 
        END as percentage
    FROM budget_details bd
    LEFT JOIN expense_voucher_items evi ON bd.sub_code = evi.general_code
    LEFT JOIN expense_vouchers ev ON evi.voucher_id = ev.id AND ev.voucher_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY bd.sub_code
    ORDER BY spent DESC
    LIMIT 8
");

// Handle PDF Generation
if (isset($_GET['action']) && $_GET['action'] == 'pdf') {
    require_once('tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Budget System');
    $pdf->SetAuthor('Budget System');
    $pdf->SetTitle('Budget Report');
    $pdf->SetSubject('Budget Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('dejavusans', '', 12);
    
    // Add title
    $pdf->Cell(0, 10, 'گزارش بودجه سیستم', 0, 1, 'C');
    $pdf->Cell(0, 10, "از تاریخ: $start_date تا: $end_date", 0, 1, 'C');
    $pdf->Ln(10);
    
    // Add summary statistics
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(0, 8, 'آمار کلی:', 0, 1, 'R');
    $pdf->SetFont('dejavusans', '', 10);
    
    $pdf->Cell(0, 6, "کل بودجه تخصیص یافته: " . number_format($total_budget, 0) . " افغانی", 0, 1, 'R');
    $pdf->Cell(0, 6, "کل هزینه شده: " . number_format($total_spent, 0) . " افغانی", 0, 1, 'R');
    $pdf->Cell(0, 6, "کل بودجه باقیمانده: " . number_format($total_remaining, 0) . " افغانی", 0, 1, 'R');
    $pdf->Cell(0, 6, "درصد کلی مصرف: " . $overall_percentage . "%", 0, 1, 'R');
    $pdf->Ln(10);
    
    // Add budget summary table
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->Cell(0, 8, 'خلاصه بودجه بر اساس دسته‌بندی:', 0, 1, 'R');
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(40, 7, 'درصد', 1, 0, 'C');
    $pdf->Cell(40, 7, 'باقیمانده', 1, 0, 'C');
    $pdf->Cell(40, 7, 'مصرف شده', 1, 0, 'C');
    $pdf->Cell(40, 7, 'بودجه', 1, 0, 'C');
    $pdf->Cell(30, 7, 'دسته‌بندی', 1, 1, 'C');
    
    // Table content
    $pdf->SetFont('dejavusans', '', 9);
    $budget_summary->data_seek(0);
    while($row = $budget_summary->fetch_assoc()) {
        $pdf->Cell(40, 7, $row['percentage'] . '%', 1, 0, 'C');
        $pdf->Cell(40, 7, number_format($row['remaining'], 0), 1, 0, 'C');
        $pdf->Cell(40, 7, number_format($row['spent'], 0), 1, 0, 'C');
        $pdf->Cell(40, 7, number_format($row['original_budget'], 0), 1, 0, 'C');
        $pdf->Cell(30, 7, $row['category'], 1, 1, 'C');
    }
    
    // Add footer
    $pdf->Ln(10);
    $pdf->SetFont('dejavusans', 'I', 8);
    $pdf->Cell(0, 6, 'تاریخ تولید گزارش: ' . date('Y/m/d H:i'), 0, 1, 'C');
    $pdf->Cell(0, 6, 'سیستم مدیریت بودجه - نسخه ۱.۰', 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('budget_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// Handle Excel Generation
if (isset($_GET['action']) && $_GET['action'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="budget_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $html = '<html dir="rtl">';
    $html .= '<head><meta charset="UTF-8"></head>';
    $html .= '<body>';
    $html .= '<h1>گزارش بودجه سیستم</h1>';
    $html .= '<h3>از تاریخ: ' . $start_date . ' تا: ' . $end_date . '</h3>';
    
    $html .= '<h3>آمار کلی:</h3>';
    $html .= '<table border="1">';
    $html .= '<tr><th>کل بودجه تخصیص یافته</th><td>' . number_format($total_budget, 0) . ' افغانی</td></tr>';
    $html .= '<tr><th>کل هزینه شده</th><td>' . number_format($total_spent, 0) . ' افغانی</td></tr>';
    $html .= '<tr><th>کل بودجه باقیمانده</th><td>' . number_format($total_remaining, 0) . ' افغانی</td></tr>';
    $html .= '<tr><th>درصد کلی مصرف</th><td>' . $overall_percentage . '%</td></tr>';
    $html .= '</table>';
    
    $html .= '<h3>خلاصه بودجه بر اساس دسته‌بندی:</h3>';
    $html .= '<table border="1">';
    $html .= '<tr>';
    $html .= '<th>دسته‌بندی</th>';
    $html .= '<th>بودجه</th>';
    $html .= '<th>مصرف شده</th>';
    $html .= '<th>باقیمانده</th>';
    $html .= '<th>درصد</th>';
    $html .= '</tr>';
    
    $budget_summary->data_seek(0);
    while($row = $budget_summary->fetch_assoc()) {
        $html .= '<tr>';
        $html .= '<td>' . $row['category'] . '</td>';
        $html .= '<td>' . number_format($row['original_budget'], 0) . '</td>';
        $html .= '<td>' . number_format($row['spent'], 0) . '</td>';
        $html .= '<td>' . number_format($row['remaining'], 0) . '</td>';
        $html .= '<td>' . $row['percentage'] . '%</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    $html .= '<p>تاریخ تولید گزارش: ' . date('Y/m/d H:i') . '</p>';
    $html .= '</body></html>';
    
    echo $html;
    exit;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد سیستم بودجه</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="asset/css/dashboard.css">
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
                <div class="user-details">
                    <h4>مدیر سیستم</h4>
                    <span>Administrator</span>
                </div>
            </div>
            <div style="font-size: 12px; opacity: 0.7; margin-top: 10px;">
                تاریخ: <?php echo date('Y/m/d'); ?>
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

        <!-- Date Filter -->
        <div class="date-filter fade-in">
            <form method="GET" action="">
                <label for="start_date">از تاریخ:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                
                <label for="end_date">تا تاریخ:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                
                <button type="submit">
                    <i class="fas fa-filter"></i>
                    فیلتر بر اساس تاریخ
                </button>
                
                <div class="report-buttons">
                    <a href="?action=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="report-btn pdf">
                        <i class="fas fa-file-pdf"></i>
                        خروجی PDF
                    </a>
                    <a href="?action=excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="report-btn excel">
                        <i class="fas fa-file-excel"></i>
                        خروجی Excel
                    </a>
                </div>
            </form>
            <div style="margin-top: 10px; font-size: 14px; color: #666;">
                <i class="fas fa-info-circle"></i>
                نمایش اطلاعات از <strong><?php echo $start_date; ?></strong> تا <strong><?php echo $end_date; ?></strong>
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
                        در بازه زمانی انتخاب شده
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
                        پس از کسر هزینه‌ها
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
                        در بازه انتخابی
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
                        <small style="font-size: 12px; color: #666; margin-right: 10px;">
                            (<?php echo $start_date; ?> تا <?php echo $end_date; ?>)
                        </small>
                    </span>
                    <a href="budget_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="view-all">
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
                        <small style="font-size: 12px; color: #666; margin-right: 10px;">
                            (<?php echo $start_date; ?> تا <?php echo $end_date; ?>)
                        </small>
                    </span>
                    <a href="list_vouchers.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="view-all">
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
                                    <?php echo htmlspecialchars($row['details'] ?? $row['expense_type']); // expense_type is now an alias for expense_type_desc ?>
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
                            هیچ فعالیتی در این بازه زمانی ثبت نشده است
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
                <br>
                <small style="opacity: 0.7;">داده‌ها بر اساس بازه زمانی انتخاب شده نمایش داده می‌شوند</small>
            </p>
            <p style="margin-top: 10px; opacity: 0.7; font-size: 12px;">جوړي شوي د انجینر حفیظ الله جهادوال لخوا</p>
        </div>
    </div>

    <!-- Mobile Toggle Button -->
    <div class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </div>

    <script src="asset/js/dashboard.js"></script>
</body>
</html>

<?php $conn->close(); ?>