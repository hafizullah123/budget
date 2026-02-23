// Toggle Sidebar for Mobile
document.getElementById("sidebarToggle").addEventListener("click", function () {
  document.querySelector(".sidebar").classList.toggle("active");
  this.innerHTML =
    document.querySelector(".sidebar").classList.contains("active") ?
      '<i class="fas fa-times"></i>'
    : '<i class="fas fa-bars"></i>';
});

// Close sidebar when clicking outside on mobile
document.addEventListener("click", function (event) {
  const sidebar = document.querySelector(".sidebar");
  const toggleBtn = document.getElementById("sidebarToggle");

  if (
    window.innerWidth <= 992 &&
    !sidebar.contains(event.target) &&
    !toggleBtn.contains(event.target) &&
    sidebar.classList.contains("active")
  ) {
    sidebar.classList.remove("active");
    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
  }
});

// Update time every minute
function updateTime() {
  const now = new Date();
  const options = {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  };
  const formatter = new Intl.DateTimeFormat("fa-IR", options);
  const dateTimeElement = document.querySelector(".date-time");
  if (dateTimeElement) {
    dateTimeElement.innerHTML = `
                    <i class="fas fa-calendar-alt"></i>
                    ${formatter.format(now).replace("،", " -")}
                `;
  }
}

// Update time immediately and then every minute
updateTime();
setInterval(updateTime, 60000);

// Animate progress bars on page load
document.addEventListener("DOMContentLoaded", function () {
  const progressBars = document.querySelectorAll(".progress-fill");
  progressBars.forEach((bar) => {
    const width = bar.style.width;
    bar.style.width = "0";
    setTimeout(() => {
      bar.style.width = width;
    }, 100);
  });
});

// Add active class to menu items based on current page
const currentPage = window.location.pathname.split("/").pop();
const menuItems = document.querySelectorAll(".menu-item");

menuItems.forEach((item) => {
  const href = item.getAttribute("href");
  if (href === currentPage) {
    item.classList.add("active");
  } else {
    item.classList.remove("active");
  }
});

// Set default date values if not set
document.addEventListener("DOMContentLoaded", function () {
  const startDateInput = document.getElementById("start_date");
  const endDateInput = document.getElementById("end_date");

  if (!startDateInput.value) {
    const firstDay = new Date();
    firstDay.setDate(1);
    startDateInput.value = firstDay.toISOString().split("T")[0];
  }

  if (!endDateInput.value) {
    const lastDay = new Date();
    lastDay.setMonth(lastDay.getMonth() + 1);
    lastDay.setDate(0);
    endDateInput.value = lastDay.toISOString().split("T")[0];
  }
});
