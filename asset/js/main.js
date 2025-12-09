// main.js - Core JavaScript functions

const API_URL = "index.php?api=true";
let currentUser = {
  id: 1,
  name: "مدیر سیستم",
  role: "admin",
};

// Core Functions
function showLoading(message = "لطفا صبر کنید...") {
  // Create loading overlay if not exists
  let loading = document.getElementById("loading");
  if (!loading) {
    loading = document.createElement("div");
    loading.id = "loading";
    loading.className =
      "fixed inset-0 bg-white bg-opacity-90 z-50 flex items-center justify-center hidden";
    loading.innerHTML = `
            <div class="text-center">
                <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-600 text-lg">${message}</p>
            </div>
        `;
    document.body.appendChild(loading);
  }
  document.querySelector("#loading p").textContent = message;
  loading.classList.remove("hidden");
}

function hideLoading() {
  const loading = document.getElementById("loading");
  if (loading) loading.classList.add("hidden");
}

function showNotification(message, type = "info") {
  const colors = {
    success: "bg-green-500",
    error: "bg-red-500",
    warning: "bg-yellow-500",
    info: "bg-blue-500",
  };

  const icons = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    warning: "fa-exclamation-triangle",
    info: "fa-info-circle",
  };

  const notification = document.createElement("div");
  notification.className = `${colors[type]} text-white p-4 rounded-lg shadow-lg animate-fade-in flex items-center fixed top-4 left-4 z-50`;
  notification.innerHTML = `
        <i class="fas ${icons[type]} ml-3"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="mr-auto text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

function formatCurrency(amount) {
  return new Intl.NumberFormat("fa-AF").format(amount) + " افغانی";
}

function closeModal() {
  const container = document.getElementById("modals-container");
  container.innerHTML = "";
}

// Event Listeners
document.addEventListener("DOMContentLoaded", function () {
  setupEventListeners();
  updateDateTime();
  setInterval(updateDateTime, 60000);
});

function setupEventListeners() {
  // Sidebar toggle
  const toggleBtn = document.getElementById("toggleSidebar");
  if (toggleBtn) {
    toggleBtn.addEventListener("click", toggleSidebar);
  }

  // Menu items
  document.querySelectorAll(".menu-item").forEach((item) => {
    item.addEventListener("click", function (e) {
      e.preventDefault();
      const page = this.getAttribute("data-page");
      window.location.href = `index.php?page=${page}`;
    });
  });
}

function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const mainContent = document.getElementById("main-content");

  sidebar.classList.toggle("collapsed");
  if (sidebar.classList.contains("collapsed")) {
    mainContent.classList.remove("mr-64");
    mainContent.classList.add("mr-20");
  } else {
    mainContent.classList.remove("mr-20");
    mainContent.classList.add("mr-64");
  }
}

function updateDateTime() {
  const now = new Date();
  const options = {
    year: "numeric",
    month: "long",
    day: "numeric",
    weekday: "long",
  };

  const persianDate = toPersianDate(now);
  const time = now.toLocaleTimeString("fa-IR");

  const dateEl = document.getElementById("current-date");
  const timeEl = document.getElementById("current-time");

  if (dateEl) dateEl.textContent = persianDate;
  if (timeEl) timeEl.textContent = time;
}

function toPersianDate(date) {
  const persianMonths = [
    "فروردین",
    "اردیبهشت",
    "خرداد",
    "تیر",
    "مرداد",
    "شهریور",
    "مهر",
    "آبان",
    "آذر",
    "دی",
    "بهمن",
    "اسفند",
  ];

  const day = date.getDate();
  const month = persianMonths[date.getMonth()];
  const year = date.getFullYear() - 621;

  return `${day} ${month} ${year}`;
}
