<?php
// includes/footer.php
?>

    <!-- Footer -->
    <footer class="bg-white border-t px-6 py-4 mt-8">
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

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<!-- Core JavaScript -->
<script src="assets/js/main.js"></script>

<?php
// Load page-specific JavaScript if exists
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$js_file = "assets/js/{$current_page}.js";

if (file_exists($js_file)): ?>
    <script src="<?php echo $js_file; ?>"></script>
<?php endif; ?>

<?php
// Load any additional scripts based on page
switch ($current_page) {
    case 'dashboard':
        echo '<script src="assets/js/dashboard.js"></script>';
        break;
    case 'budget-periods':
        echo '<script src="assets/js/budget-periods.js"></script>';
        break;
    case 'manual-entry':
        echo '<script src="assets/js/manual-entry.js"></script>';
        break;
    case 'reports':
        echo '<script src="assets/js/reports.js"></script>';
        break;
}
?>