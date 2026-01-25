/**
 * Theme Toggle Functionality
 * EduID - Educational Identity Verification System
 */

// Get the current theme from localStorage or default to 'light'
function getTheme() {
  return localStorage.getItem("theme") || "light";
}

// Set the theme
function setTheme(theme) {
  document.documentElement.setAttribute("data-theme", theme);
  localStorage.setItem("theme", theme);
  updateThemeIcon(theme);
}

// Update theme toggle icon
function updateThemeIcon(theme) {
  const themeToggle = document.getElementById("themeToggle");
  const themeToggleTop = document.getElementById("themeToggleTop");

  if (themeToggle) {
    const icon = themeToggle.querySelector("i");
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

  if (themeToggleTop) {
    const icon = themeToggleTop.querySelector("i");
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
}

// Toggle between light and dark theme
function toggleTheme() {
  const currentTheme = getTheme();
  const newTheme = currentTheme === "light" ? "dark" : "light";
  setTheme(newTheme);
}

// Initialize theme on page load
document.addEventListener("DOMContentLoaded", function () {
  const savedTheme = getTheme();
  setTheme(savedTheme);

  // Add event listener to theme toggle buttons
  const themeToggle = document.getElementById("themeToggle");
  const themeToggleTop = document.getElementById("themeToggleTop");

  if (themeToggle) {
    themeToggle.addEventListener("click", toggleTheme);
  }

  if (themeToggleTop) {
    themeToggleTop.addEventListener("click", toggleTheme);
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
