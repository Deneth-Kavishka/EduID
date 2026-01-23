(() => {
  const root = document.documentElement;
  const toggle = document.querySelector("[data-theme-toggle]");
  const stored = localStorage.getItem("eduid-theme");
  if (stored) {
    root.setAttribute("data-theme", stored);
  }

  if (toggle) {
    toggle.addEventListener("click", () => {
      const current = root.getAttribute("data-theme") || "light";
      const next = current === "light" ? "dark" : "light";
      root.setAttribute("data-theme", next);
      localStorage.setItem("eduid-theme", next);
    });
  }
})();
