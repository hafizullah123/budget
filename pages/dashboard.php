<?php
// pages/dashboard.php
?>

<div id="dashboard-content" class="content-section">
    <div class="animate-fade-in">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6 card-hover">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-blue-100 text-sm">کل بودجه ثبت شده</p>
                        <p class="text-2xl font-bold mt-2" id="total-budget">0 افغانی</p>
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
            <!-- More stats cards -->
        </div>
        
        <!-- Charts and Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
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
                <div id="recent-activities" class="space-y-4">
                    <!-- Loaded via JS -->
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-6">اقدامات سریع</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <button onclick="window.location.href='index.php?page=manual-entry'" class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg p-4 text-center transition-all duration-200">
                    <i class="fas fa-edit text-blue-600 text-2xl mb-2"></i>
                    <p class="font-medium text-blue-800">ورود دستی بودجه</p>
                    <p class="text-blue-600 text-sm mt-1">ثبت مقادیر جدید</p>
                </button>
                <!-- More action buttons -->
            </div>
        </div>
        
        <!-- Active Periods -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">دوره‌های بودجه فعال</h3>
                <a href="index.php?page=budget-periods" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    مشاهده همه
                    <i class="fas fa-arrow-left mr-1"></i>
                </a>
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
                    <tbody id="active-periods-table">
                        <!-- Loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Dashboard-specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
});

async function loadDashboardData() {
    showLoading('در حال بارگذاری داشبورد...');
    
    try {
        const response = await fetch(`${API_URL}&action=dashboard_stats`);
        const data = await response.json();
        
        if (data.success) {
            updateDashboard(data.data);
        } else {
            showError('خطا در بارگذاری داشبورد');
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showError('خطا در ارتباط با سرور');
    }
    
    hideLoading();
}

function updateDashboard(data) {
    // Update stats
    document.getElementById('total-budget').textContent = formatCurrency(data.total_budget);
    
    // Update recent activities
    document.getElementById('recent-activities').innerHTML = renderRecentActivities(data.recent_activities || []);
    
    // Update active periods table
    document.getElementById('active-periods-table').innerHTML = renderActivePeriods(data.active_periods_list || []);
    
    // Initialize chart
    initializeBudgetChart();
}
</script>