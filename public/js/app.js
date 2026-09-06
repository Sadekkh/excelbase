(() => {
  const token = () => document.querySelector('meta[name="csrf-token"]')?.content || "";

  window.Baserow = window.Baserow || {};

  window.Baserow.api = async function api(url, options = {}) {
    const headers = {
      Accept: "application/json",
      "X-CSRF-TOKEN": token(),
      "X-Requested-With": "XMLHttpRequest",
      ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }),
      ...(options.headers || {}),
    };
    const res = await fetch(url, { ...options, headers });
    if (res.status === 204) return null;
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const message = data.message || data.errors && Object.values(data.errors).flat()[0] || "Something went wrong.";
      throw new Error(message);
    }
    return data;
  };

  window.Baserow.toast = function toast(message) {
    const root = document.getElementById("toast-root");
    if (!root) return;
    const el = document.createElement("div");
    el.className = "toast";
    el.textContent = message;
    root.appendChild(el);
    setTimeout(() => el.remove(), 2800);
  };

  window.Baserow.closeMenus = function closeMenus() {
    document.querySelectorAll(".menu").forEach((m) => {
      m.hidden = true;
    });
  };

  function placeMenu(menu, anchor) {
    const rect = anchor.getBoundingClientRect();
    menu.hidden = false;
    menu.style.position = "fixed";
    const top = rect.bottom + 4;
    let left = rect.left;
    const mw = menu.offsetWidth;
    if (left + mw > window.innerWidth - 8) left = window.innerWidth - mw - 8;
    menu.style.top = `${top}px`;
    menu.style.left = `${Math.max(8, left)}px`;
  }

  document.addEventListener("click", (e) => {
    const opener = e.target.closest("[data-menu]");
    if (opener) {
      e.preventDefault();
      e.stopPropagation();
      const id = opener.getAttribute("data-menu");
      const menu = document.getElementById(id);
      if (!menu) return;
      const wasOpen = !menu.hidden;
      window.Baserow.closeMenus();
      if (!wasOpen) placeMenu(menu, opener);
      return;
    }
    if (!e.target.closest(".menu")) window.Baserow.closeMenus();
  });

  document.addEventListener("click", (e) => {
    const open = e.target.closest("[data-open-modal]");
    if (open) {
      const modal = document.getElementById(open.getAttribute("data-open-modal"));
      if (modal) modal.hidden = false;
    }
    if (e.target.closest("[data-close-modal]")) {
      e.target.closest(".modal").hidden = true;
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal:not([hidden])").forEach((m) => (m.hidden = true));
      window.Baserow.closeMenus();
    }
    if (e.key === "/" && !["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement.tagName)) {
      const search = document.getElementById("sidebar-search") || document.getElementById("grid-search");
      if (search) {
        e.preventDefault();
        search.focus();
      }
    }
  });

  const sidebarSearch = document.getElementById("sidebar-search");
  if (sidebarSearch) {
    sidebarSearch.addEventListener("input", () => {
      const q = sidebarSearch.value.trim().toLowerCase();
      document.querySelectorAll("[data-search-text]").forEach((el) => {
        const match = !q || el.getAttribute("data-search-text").includes(q);
        el.style.display = match ? "" : "none";
        if (match && el.closest("details")) el.closest("details").open = true;
      });
    });
  }
})();
