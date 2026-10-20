async function loadComponent(id, file) {
    const response = await fetch(file);
    document.getElementById(id).innerHTML = await response.text();
}

async function initLayout() {

    await loadComponent("header-container", "components/header.html");
    await loadComponent("sidebar-container", "components/sidebar.html");

    // Current Date
    const currentDate = document.getElementById("currentDate");
    if (currentDate) {
        currentDate.textContent = new Date().toLocaleDateString("en-US", {
            weekday: "short",
            year: "numeric",
            month: "short",
            day: "numeric"
        });
    }

    // Sidebar Toggle
    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("main-sidebar");
    const content = document.getElementById("main-content");

    if (menuToggle && sidebar && content) {
        menuToggle.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
            content.classList.toggle("ml-80");
        });
    }

    // Theme Toggle
    const themeToggle = document.getElementById("theme-toggle");
    if (themeToggle) {
        themeToggle.addEventListener("change", () => {
            document.documentElement.classList.toggle("dark");
        });
    }

    // Dropdown
    document.querySelectorAll(".dropdown-toggle").forEach(btn => {
        btn.addEventListener("click", () => {
            const menu = btn.nextElementSibling;
            menu.classList.toggle("hidden");

            btn.querySelector(".fa-chevron-right")
                ?.classList.toggle("rotate-90");
        });
    });

}

document.addEventListener("DOMContentLoaded", initLayout);