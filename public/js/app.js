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
    const root = document.getElementById("menu-root") || document.body;
    if (menu.parentElement !== root) {
      root.appendChild(menu);
    }
    const rect = anchor.getBoundingClientRect();
    menu.hidden = false;
    menu.style.position = "fixed";
    menu.style.zIndex = "60";
    const mw = Math.max(menu.offsetWidth, 200);
    const mh = menu.offsetHeight || 8;
    let left = rect.left;
    if (left + mw > window.innerWidth - 8) left = rect.right - mw;
    left = Math.min(Math.max(8, left), window.innerWidth - mw - 8);
    const spaceBelow = window.innerHeight - rect.bottom - 8;
    const spaceAbove = rect.top - 8;
    let top = rect.bottom + 4;
    if (spaceBelow < mh && spaceAbove > spaceBelow) {
      top = Math.max(8, rect.top - mh - 4);
    } else if (top + mh > window.innerHeight - 8) {
      top = Math.max(8, window.innerHeight - mh - 8);
    }
    menu.style.top = `${Math.round(top)}px`;
    menu.style.left = `${Math.round(left)}px`;
    menu.style.minWidth = `${Math.max(mw, rect.width)}px`;
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

  window.addEventListener("resize", () => window.Baserow.closeMenus());

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

  const sidebarToggle = document.getElementById("sidebar-toggle");
  const sidebarBackdrop = document.getElementById("sidebar-backdrop");
  const appShell = document.getElementById("app");
  function setSidebar(open) {
    if (!appShell) return;
    appShell.classList.toggle("sidebar-open", open);
    if (sidebarBackdrop) sidebarBackdrop.hidden = !open;
  }
  sidebarToggle?.addEventListener("click", () => setSidebar(!appShell.classList.contains("sidebar-open")));
  sidebarBackdrop?.addEventListener("click", () => setSidebar(false));

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
