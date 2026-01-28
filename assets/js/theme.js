/**
 * Theme Toggle & Navigation Enhancement
 * EduID - Educational Identity Verification System
 */

// ==========================================
// THEME FUNCTIONALITY
// ==========================================

// Get the current theme from localStorage or default to 'light'
function getTheme() {
  return localStorage.getItem("theme") || "light";
}

// Set the theme
function setTheme(theme) {
  document.documentElement.setAttribute("data-theme", theme);
  document.body.setAttribute("data-theme", theme);
  localStorage.setItem("theme", theme);
  updateThemeIcon(theme);
}

// Update theme toggle icon
function updateThemeIcon(theme) {
  // Get all theme toggle buttons
  const toggles = document.querySelectorAll(
    "#themeToggle, #themeToggleTop, .theme-toggle",
  );

  toggles.forEach((toggle) => {
    if (toggle) {
      const icon = toggle.querySelector("i");
      if (icon) {
        if (theme === "dark") {
          icon.classList.remove("fa-moon");
          icon.classList.add("fa-sun");
        } else {
          icon.classList.remove("fa-sun");
          icon.classList.add("fa-moon");
        }
      }
    }
  });
}

// Toggle between light and dark theme
function toggleTheme(e) {
  if (e) {
    e.preventDefault();
    e.stopPropagation();
  }
  const currentTheme = getTheme();
  const newTheme = currentTheme === "light" ? "dark" : "light";
  setTheme(newTheme);
}

// ==========================================
// SIDEBAR SCROLL POSITION PERSISTENCE
// ==========================================

// Save sidebar scroll position
function saveSidebarScroll() {
  const sidebar = document.querySelector(".sidebar-nav");
  if (sidebar) {
    sessionStorage.setItem("sidebarScrollTop", sidebar.scrollTop);
  }
}

// Restore sidebar scroll position
function restoreSidebarScroll() {
  const sidebar = document.querySelector(".sidebar-nav");
  const savedScroll = sessionStorage.getItem("sidebarScrollTop");
  if (sidebar && savedScroll) {
    sidebar.scrollTop = parseInt(savedScroll, 10);
  }
}

// ==========================================
// PAGE TRANSITION LOADER
// ==========================================

// Create and inject the page loader
function createPageLoader() {
  // Check if loader already exists
  if (document.getElementById("pageLoader")) return;

  const loader = document.createElement("div");
  loader.id = "pageLoader";
  loader.innerHTML = `
    <div class="loader-content">
      <div class="loader-spinner"></div>
      <span>Loading...</span>
    </div>
  `;

  // Add loader styles
  const style = document.createElement("style");
  style.textContent = `
    #pageLoader {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: var(--bg-primary, #ffffff);
      z-index: 99999;
      display: none;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.2s ease;
    }
    #pageLoader.active {
      display: flex;
      opacity: 1;
    }
    #pageLoader .loader-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
      color: var(--text-secondary, #64748b);
      font-family: 'Inter', sans-serif;
      font-size: 0.875rem;
    }
    #pageLoader .loader-spinner {
      width: 40px;
      height: 40px;
      border: 3px solid var(--border-color, #e2e8f0);
      border-top-color: var(--primary-color, #6366f1);
      border-radius: 50%;
      animation: loaderSpin 0.8s linear infinite;
    }
    @keyframes loaderSpin {
      to { transform: rotate(360deg); }
    }
    
    /* Page fade-in animation */
    .page-transition-enter {
      animation: pageEnter 0.3s ease forwards;
    }
    @keyframes pageEnter {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Sidebar nav-item click feedback */
    .sidebar-nav .nav-item {
      transition: all 0.2s ease;
    }
    .sidebar-nav .nav-item.navigating {
      background: var(--primary-color, #6366f1) !important;
      color: white !important;
      transform: scale(0.98);
    }
    .sidebar-nav .nav-item.navigating i,
    .sidebar-nav .nav-item.navigating span {
      color: white !important;
    }
  `;

  document.head.appendChild(style);
  document.body.appendChild(loader);
}

// Show page loader
function showPageLoader() {
  const loader = document.getElementById("pageLoader");
  if (loader) {
    loader.classList.add("active");
  }
}

// Hide page loader
function hidePageLoader() {
  const loader = document.getElementById("pageLoader");
  if (loader) {
    loader.classList.remove("active");
  }
}

// ==========================================
// NAVIGATION ENHANCEMENT
// ==========================================

// Handle sidebar navigation clicks
function setupSidebarNavigation() {
  const sidebarLinks = document.querySelectorAll(".sidebar-nav .nav-item");

  sidebarLinks.forEach((link) => {
    // Skip if it's a logout link or external link
    const href = link.getAttribute("href");
    if (
      !href ||
      href.includes("logout") ||
      href.startsWith("http") ||
      href.startsWith("#")
    ) {
      return;
    }

    link.addEventListener("click", function (e) {
      // Don't do anything if clicking the active page
      if (this.classList.contains("active")) {
        e.preventDefault();
        return;
      }

      // Save sidebar scroll position
      saveSidebarScroll();

      // Add visual feedback
      this.classList.add("navigating");

      // Show loader for smoother transition
      showPageLoader();
    });
  });
}

// Scroll active nav item into view (centered in sidebar)
function scrollActiveNavIntoView() {
  const activeItem = document.querySelector(".sidebar-nav .nav-item.active");
  const sidebarNav = document.querySelector(".sidebar-nav");

  if (activeItem && sidebarNav) {
    // Get the saved scroll position first
    const savedScroll = sessionStorage.getItem("sidebarScrollTop");

    if (savedScroll) {
      // Use saved scroll position
      sidebarNav.scrollTop = parseInt(savedScroll, 10);
    } else {
      // Calculate position to center active item in sidebar
      const sidebarRect = sidebarNav.getBoundingClientRect();
      const itemRect = activeItem.getBoundingClientRect();
      const itemOffsetTop = activeItem.offsetTop;
      const sidebarHeight = sidebarNav.clientHeight;
      const itemHeight = activeItem.clientHeight;

      // Center the item in the visible area
      const scrollTo = itemOffsetTop - sidebarHeight / 2 + itemHeight / 2;

      // Only scroll if item is not fully visible
      if (scrollTo > 0) {
        sidebarNav.scrollTop = scrollTo;
      }
    }
  }
}

// ==========================================
// INITIALIZATION
// ==========================================

// Initialize everything on page load
document.addEventListener("DOMContentLoaded", function () {
  // Initialize theme
  const savedTheme = getTheme();
  setTheme(savedTheme);

  // Add event listener to ALL theme toggle buttons
  const allThemeToggles = document.querySelectorAll(
    "#themeToggle, #themeToggleTop, .theme-toggle",
  );

  allThemeToggles.forEach((toggle) => {
    if (toggle) {
      // Remove any existing listeners by cloning
      const newToggle = toggle.cloneNode(true);
      toggle.parentNode.replaceChild(newToggle, toggle);

      // Add fresh click listener
      newToggle.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleTheme();
      });
    }
  });

  // Create page loader
  createPageLoader();

  // Setup sidebar navigation enhancement
  setupSidebarNavigation();

  // Restore sidebar scroll position or scroll active item into view
  restoreSidebarScroll();

  // Add page enter animation to main content
  const mainContent = document.querySelector(".main-content");
  if (mainContent) {
    mainContent.classList.add("page-transition-enter");
  }

  // Clear the saved scroll position after a short delay
  // This ensures fresh navigation to different sections works properly
  setTimeout(() => {
    sessionStorage.removeItem("sidebarScrollTop");
  }, 1000);
});

// Handle page visibility for better navigation
document.addEventListener("visibilitychange", function () {
  if (document.visibilityState === "visible") {
    hidePageLoader();
  }
});

// Hide loader when page fully loads (fallback)
window.addEventListener("load", function () {
  hidePageLoader();

  // Ensure scroll position is correct after full load
  setTimeout(scrollActiveNavIntoView, 100);
});

// Handle browser back/forward navigation
window.addEventListener("pageshow", function (event) {
  hidePageLoader();

  // If coming from cache (back button), restore scroll
  if (event.persisted) {
    restoreSidebarScroll();
  }
});

// Smooth scroll for anchor links
document.addEventListener("DOMContentLoaded", function () {
  const links = document.querySelectorAll('a[href^="#"]');
  links.forEach((link) => {
    link.addEventListener("click", function (e) {
      const href = this.getAttribute("href");
      if (href !== "#") {
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          target.scrollIntoView({
            behavior: "smooth",
            block: "start",
          });
        }
      }
    });
  });
});
