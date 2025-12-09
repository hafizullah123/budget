<!-- includes/sidebar.php -->

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
        <a href="index.php?page=dashboard" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200 <?php echo ($page == 'dashboard') ? 'active bg-blue-800' : ''; ?>">
            <i class="fas fa-home ml-3"></i>
            <span class="menu-text">داشبورد</span>
        </a>
        
        <a href="index.php?page=budget-periods" class="menu-item flex items-center p-3 text-blue-100 hover:bg-blue-800 rounded-lg transition-all duration-200 <?php echo ($page == 'budget-periods') ? 'active bg-blue-800' : ''; ?>">
            <i class="fas fa-calendar-alt ml-3"></i>
            <span class="menu-text">دوره‌های بودجه</span>
        </a>
        
        <!-- More menu items -->
    </nav>
    
    <!-- Bottom Section -->
    <div class="absolute bottom-0 w-full p-4 border-t border-blue-700 bg-blue-900">
        <div class="space-y-2">
            <a href="index.php?page=settings" class="flex items-center p-2 text-blue-100 hover:bg-blue-800 rounded-lg">
                <i class="fas fa-cog ml-2"></i>
                <span class="text-sm">تنظیمات</span>
            </a>
            
            <a href="logout.php" id="logout-btn" class="flex items-center p-2 text-red-200 hover:bg-red-900 rounded-lg">
                <i class="fas fa-sign-out-alt ml-2"></i>
                <span class="text-sm">خروج از سیستم</span>
            </a>
        </div>
    </div>
</div>