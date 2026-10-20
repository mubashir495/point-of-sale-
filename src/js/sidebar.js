
(function () {
  "use strict";

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }

  function init() {
    const sidebar = document.getElementById("main-sidebar");
    if (!sidebar) return; // sidebar partial not present on this page

    const menuToggle = document.getElementById("menuToggle");
    const overlay = document.getElementById("sidebarOverlay");
    const themeToggle = document.getElementById("theme-toggle");
    const currentDateEl = document.getElementById("currentDate");

    initMobileToggle({ sidebar, menuToggle, overlay });
    const dropdownGroups = initAccordionDropdowns(sidebar);
    highlightActiveLink(sidebar, dropdownGroups);
    initThemeToggle(themeToggle);
    initClock(currentDateEl);
    initResizeHandling({ sidebar, overlay, dropdownGroups });
  }

  /* ------------------------------------------------------------------
   * 1. Mobile open / close + overlay
   * ------------------------------------------------------------------ */
  function initMobileToggle({ sidebar, menuToggle, overlay }) {
    const LG_BREAKPOINT = 1024; // matches Tailwind's `lg:` breakpoint
    const isDesktop = () => window.innerWidth >= LG_BREAKPOINT;

    function openSidebar() {
      sidebar.classList.remove("-translate-x-full");
      overlay?.classList.remove("hidden");
      menuToggle?.setAttribute("aria-expanded", "true");
      document.body.classList.add("overflow-hidden");
    }

    function closeSidebar() {
      if (isDesktop()) return; // never hide on desktop
      sidebar.classList.add("-translate-x-full");
      overlay?.classList.add("hidden");
      menuToggle?.setAttribute("aria-expanded", "false");
      document.body.classList.remove("overflow-hidden");
    }

    function toggleSidebar() {
      const isOpen = !sidebar.classList.contains("-translate-x-full");
      isOpen ? closeSidebar() : openSidebar();
    }

    menuToggle?.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleSidebar();
    });

    overlay?.addEventListener("click", closeSidebar);

    // Close on outside click (mobile only)
    document.addEventListener("click", (e) => {
      if (isDesktop()) return;
      const clickedInsideSidebar = sidebar.contains(e.target);
      const clickedToggle = menuToggle?.contains(e.target);
      if (!clickedInsideSidebar && !clickedToggle) closeSidebar();
    });

    // Close sidebar when a nav link is selected (mobile only)
    sidebar.querySelectorAll("[data-nav-link]").forEach((link) => {
      link.addEventListener("click", () => {
        if (!isDesktop()) closeSidebar();
      });
    });

    // Exposed so initResizeHandling can reuse the same close logic
    sidebar._closeMobileSidebar = closeSidebar;
    sidebar._isDesktop = isDesktop;
  }

  /* ------------------------------------------------------------------
   * 2. Accordion dropdowns — single animation system.
   *    Only `max-height` (inline style) drives the open/close animation;
   *    `aria-expanded` drives all visual state (chevron rotation, active
   *    color) via CSS attribute selectors. No `.open`/`.hidden` classes,
   *    no double listeners.
   * ------------------------------------------------------------------ */
  function initAccordionDropdowns(sidebar) {
    const groups = Array.from(sidebar.querySelectorAll("[data-dropdown-toggle]")).map((toggle) => {
      const menu = document.getElementById(toggle.getAttribute("aria-controls"));
      return { toggle, menu };
    });

    function open(group) {
      group.toggle.setAttribute("aria-expanded", "true");
      group.menu.style.maxHeight = `${group.menu.scrollHeight}px`;
    }

    function close(group) {
      group.toggle.setAttribute("aria-expanded", "false");
      group.menu.style.maxHeight = "0px";
    }

    function isOpen(group) {
      return group.toggle.getAttribute("aria-expanded") === "true";
    }

    function handleToggle(group) {
      const wasOpen = isOpen(group);
      // Accordion: close every other group first
      groups.forEach((g) => g !== group && close(g));
      wasOpen ? close(group) : open(group);
    }

    groups.forEach((group) => {
      // Single click listener per toggle
      group.toggle.addEventListener("click", () => handleToggle(group));

      // Basic keyboard support (Enter / Space) — buttons get this for
      // free natively, but we guard against non-button elements too.
      group.toggle.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          handleToggle(group);
        }
      });

      close(group); // ensure a clean, consistent closed state on load
    });

    return { groups, open, close, isOpen };
  }

  /* ------------------------------------------------------------------
   * 3. Highlight the current page and auto-expand its parent dropdown.
   * ------------------------------------------------------------------ */
  function highlightActiveLink(sidebar, { groups, open }) {
    const currentFile = window.location.pathname.split("/").pop() || "dashboard.html";

    sidebar.querySelectorAll("[data-nav-link]").forEach((link) => {
      const linkFile = link.getAttribute("href").split("/").pop();
      if (linkFile !== currentFile) return;

      const parentMenu = link.closest("[data-dropdown-menu]");

      if (parentMenu) {
        // Sub-link inside a dropdown: swap its resting border/bg for the
        // same "active" look the hover state uses.
        link.classList.remove("border-transparent");
        link.classList.add("bg-slate-700", "text-white", "border-yellow-400", "font-semibold");

        const group = groups.find((g) => g.menu === parentMenu);
        if (group) open(group); // expand only the active item's parent
      } else {
        // Top-level link (e.g. Dashboard)
        link.classList.add("border-l-4", "border-amber-400", "bg-white/5", "text-white");
      }
    });
  }

  /* ------------------------------------------------------------------
   * 4. Theme toggle — single implementation, persisted to localStorage.
   * ------------------------------------------------------------------ */
  function initThemeToggle(themeToggle) {
    const THEME_KEY = "mb-theme";

    function applyTheme(theme) {
      document.documentElement.classList.toggle("dark", theme === "dark");
      if (themeToggle) themeToggle.checked = theme === "dark";
    }

    const savedTheme =
      localStorage.getItem(THEME_KEY) ||
      (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
    applyTheme(savedTheme);

    themeToggle?.addEventListener("change", () => {
      const theme = themeToggle.checked ? "dark" : "light";
      localStorage.setItem(THEME_KEY, theme);
      applyTheme(theme);
    });
  }

  /* ------------------------------------------------------------------
   * 5. Live header clock — single implementation.
   * ------------------------------------------------------------------ */
  function initClock(currentDateEl) {
    if (!currentDateEl) return;

    const update = () => {
      currentDateEl.textContent = new Date().toLocaleString("en-US", {
        weekday: "short",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
    };

    update();
    const timerId = setInterval(update, 60000);

    // Avoid leaking the timer if the sidebar partial is ever removed
    // from the DOM dynamically (e.g. SPA-style navigation).
    window.addEventListener("beforeunload", () => clearInterval(timerId), { once: true });
  }

  /* ------------------------------------------------------------------
   * 6. Resize handling — keep mobile/desktop state consistent, and
   *    recalculate any open dropdown's height (text can reflow at
   *    different widths).
   * ------------------------------------------------------------------ */
  function initResizeHandling({ sidebar, overlay, dropdownGroups }) {
    let resizeTimer = null;

    window.addEventListener("resize", () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        if (sidebar._isDesktop()) {
          overlay?.classList.add("hidden");
          document.body.classList.remove("overflow-hidden");
        }
        dropdownGroups.groups.forEach((group) => {
          if (dropdownGroups.isOpen(group)) {
            group.menu.style.maxHeight = `${group.menu.scrollHeight}px`;
          }
        });
      }, 100);
    });
  }
})();