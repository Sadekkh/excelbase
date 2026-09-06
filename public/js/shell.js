(() => {
  const bootEl = document.getElementById("shell-boot");
  if (!bootEl) return;

  let boot = JSON.parse(bootEl.textContent);
  const api = window.Baserow.api;
  const toast = window.Baserow.toast;
  const app = document.getElementById("app");
  const nav = document.getElementById("sidebar-nav");
  const chrome = document.getElementById("sheet-chrome");
  const stage = document.getElementById("view-stage");
  const drawer = document.getElementById("row-drawer");
  let current = { kind: null, tableId: null, viewId: null, dashboardId: null, search: "" };
  let searchTimer = null;

  const ICONS = {
    table: "M3.5 5h17v14h-17zM3.5 9.5h17M9 5v14",
    database: "M5 7c0-1.7 3.1-3 7-3s7 1.3 7 3-3.1 3-7 3-7-1.3-7-3zm0 0v10c0 1.7 3.1 3 7 3s7-1.3 7-3V7",
    board: "M5 19V9M11 19V5M17 19v-7M3 19h18",
    users: "M9 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3.5 18c.6-3 2.8-4.5 5.5-4.5S14 15 14.6 18M16.5 9.2a2.3 2.3 0 1 0 0-4.6 2.3 2.3 0 0 0 0 4.6z",
    bolt: "M13 2 4 14h7l-1 8 9-12h-7z",
    plan: "M12 3.8 14.4 9l5.6.8-4 4 1 5.6L12 16.8 6.9 19.4l1-5.6-4-4L9.6 9 12 3.8z",
    look: "M12 4.5a7.5 7.5 0 1 0 0 15 7.5 7.5 0 0 0 0-15zM12 8v4l2.5 1.5",
    plus: "M12 6v12M6 12h12",
    search: "M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13zM16 16.5 20 20.5",
    filter: "M4 6h16l-6 7.2V18l-4 2v-6.8L4 6z",
    sort: "M8 7v10M8 7l-2.5 2.5M8 7l2.5 2.5M16 17V7M16 17l-2.5-2.5M16 17l2.5-2.5",
    hide: "M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6z",
    grid: "M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z",
    lock: "M8.5 10V7.5a3.5 3.5 0 0 1 7 0V10M6 10h12v10H6z",
    inbox: "M5 5h14v10H9l-4 4z",
    logout: "M10 7V5.5A1.5 1.5 0 0 1 11.5 4h7A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 10 18.5V17M4 12h10M7 9l-3 3 3 3",
    home: "M4 11 12 4l8 7v9H4z",
    admin: "M12 4.5v1.8M12 17.7V19.5M4.5 12h1.8M17.7 12H19.5M6.4 6.4l1.3 1.3M16.3 16.3l1.3 1.3",
    x: "M6 6l12 12M18 6 6 18",
  };

  function icon(name, size = 14) {
    const d = ICONS[name] || ICONS.table;
    return `<svg class="icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
  }

  function esc(s) {
    return String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  let mode = boot.can_build && boot.surface === "builder" ? "build" : "use";

  function isBuild() {
    return mode === "build" && boot.can_build;
  }

  function closeSidebar() {
    app.classList.remove("sidebar-open");
    const backdrop = document.getElementById("sidebar-backdrop");
    if (backdrop) backdrop.hidden = true;
  }

  function setBusy(on) {
    stage.classList.toggle("is-busy", on);
  }

  function renderMode() {
    const box = document.getElementById("mode-switch");
    const caption = document.getElementById("mode-caption");
    if (boot.can_build) {
      box.hidden = false;
      box.querySelectorAll("[data-surface]").forEach((btn) => {
        btn.classList.toggle("is-on", btn.dataset.surface === boot.surface);
      });
      caption.textContent = isBuild()
        ? "Build this workspace — look, structure, and access"
        : "Work in this workspace’s sheets";
    } else {
      box.hidden = true;
      caption.textContent = "Sheets you belong to in this workspace";
    }
    app.dataset.surface = boot.surface;
    const role = document.getElementById("user-role-label");
    if (role) role.textContent = boot.role_label;
    document.querySelector(".sidebar__workspace-name").textContent = boot.workspace.name;
    applyBrand();
  }

  function hexLuma(hex) {
    const n = String(hex || "").replace("#", "");
    if (n.length !== 6) return 1;
    const r = parseInt(n.slice(0, 2), 16);
    const g = parseInt(n.slice(2, 4), 16);
    const b = parseInt(n.slice(4, 6), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  }

  function applyBrand() {
    const w = boot.workspace || {};
    const brand = w.brand_color || "#5190ef";
    const sidebar = w.sidebar_color || "#fafafa";
    const dark = hexLuma(sidebar) < 0.55;
    app.style.setProperty("--brand", brand);
    app.style.setProperty("--sidebar-bg", sidebar);
    app.classList.toggle("is-dark-sidebar", dark);
    const logo = document.getElementById("brand-logo");
    const mark = document.getElementById("brand-mark");
    const avatarImg = document.getElementById("workspace-avatar-img");
    const letter = document.getElementById("workspace-avatar-letter");
    if (logo) {
      if (w.logo_url) {
        logo.src = w.logo_url;
        logo.hidden = false;
      } else {
        logo.removeAttribute("src");
        logo.hidden = true;
      }
    }
    if (mark) mark.hidden = !!w.logo_url;
    if (avatarImg) {
      if (w.logo_url) {
        avatarImg.src = w.logo_url;
        avatarImg.hidden = false;
      } else {
        avatarImg.removeAttribute("src");
        avatarImg.hidden = true;
      }
    }
    if (letter) {
      letter.hidden = !!w.logo_url;
      letter.textContent = String(w.name || "?").slice(0, 1).toUpperCase();
    }
  }

  function flattenTree(nodes, depth = 0) {
    const out = [];
    (nodes || []).forEach((node) => {
      out.push({ ...node, depth });
      flattenTree(node.children || [], depth + 1).forEach((child) => out.push(child));
    });
    return out;
  }

  function renderWorkspaceMenu() {
    const menu = document.getElementById("workspace-menu");
    if (!menu) return;
    const items = flattenTree(boot.tree || []).map((ws) => `
      <button type="button" data-open-workspace="${ws.id}" class="${ws.id === boot.workspace.id ? "is-active" : ""}" style="padding-left:${12 + ws.depth * 14}px">${esc(ws.name)}</button>
    `).join("");
    menu.innerHTML = items + `<div class="menu__sep"></div>
      ${boot.can_build ? `<button type="button" data-create-workspace>New workspace</button>` : ""}`;
  }

  function renderUserMenu() {
    const menu = document.getElementById("user-menu");
    if (!menu) return;
    menu.innerHTML = `
      <button type="button" data-go="${boot.urls.inbox}">Inbox${boot.unread ? ` (${boot.unread})` : ""}</button>
      ${boot.user.is_platform_admin ? `<button type="button" data-go="${boot.urls.admin}">Platform admin</button>` : ""}
      <div class="menu__sep"></div>
      <form method="post" action="${boot.urls.logout}">
        <input type="hidden" name="_token" value="${boot.urls.csrf}">
        <button type="submit">Sign out</button>
      </form>
    `;
  }

  function navButton(opts) {
    const active = opts.active ? " is-active" : "";
    return `<button type="button" class="nav-item${active}" data-nav="${opts.nav}" ${opts.attrs || ""}>
      ${icon(opts.icon)}${esc(opts.label)}
    </button>`;
  }

  function renderNav() {
    const q = (document.getElementById("sidebar-search")?.value || "").trim().toLowerCase();
    const match = (name) => !q || name.toLowerCase().includes(q);
    let html = "";

    if (isBuild()) {
      html += `<p class="nav-label">Build</p>`;
      html += navButton({ nav: "look", icon: "look", label: "Look", active: current.kind === "look" });
      html += navButton({ nav: "structure", icon: "database", label: "Structure", active: current.kind === "structure" });
      html += navButton({ nav: "automations", icon: "bolt", label: "Automations", active: current.kind === "automations" });
      if (boot.can_manage) {
        html += navButton({ nav: "people", icon: "users", label: "People", active: current.kind === "people" });
        html += navButton({ nav: "plan", icon: "plan", label: "Plan", active: current.kind === "plan" });
      }
      html += `<p class="nav-label">Child workspaces</p>`;
      const kids = flattenTree(boot.tree || []).filter((ws) => ws.parent_id === boot.workspace.id);
      if (!kids.length) html += `<div class="nav-empty">No child workspaces yet.</div>`;
      kids.forEach((ws) => {
        html += navButton({
          nav: "workspace",
          icon: "database",
          label: ws.name,
          active: false,
          attrs: `data-id="${ws.id}"`,
        });
      });
      html += `<button type="button" class="nav-item" data-create-workspace data-parent="${boot.workspace.id}">${icon("plus")} New child workspace</button>`;
      html += `<p class="nav-label">Sheets to edit</p>`;
    } else {
      if (boot.dashboards.length) {
        html += `<p class="nav-label">Dashboards</p>`;
        boot.dashboards.forEach((board) => {
          if (!match(board.name)) return;
          html += navButton({
            nav: "board",
            icon: "board",
            label: board.name,
            active: current.kind === "board" && current.dashboardId === board.id,
            attrs: `data-id="${board.id}"`,
          });
        });
      }
      html += `<p class="nav-label">Sheets</p>`;
    }

    boot.databases.forEach((db) => {
      const tables = db.tables.filter((t) => match(t.name) || match(db.name));
      if (!tables.length && q) return;
      html += `<details class="tree-db" open>
        <summary>
          <span class="tree-db__icon">${icon("database")}</span>
          <span class="tree-db__name">${esc(db.name)}</span>
        </summary>
        <ul class="tree-tables">`;
      tables.forEach((tbl) => {
        const on = current.kind === "sheet" && current.tableId === tbl.id;
        html += `<li>
          <button type="button" class="tree-table${on ? " is-active" : ""}" data-nav="sheet" data-id="${tbl.id}">
            ${icon("table")}<span>${esc(tbl.name)}</span>
          </button>
        </li>`;
      });
      html += `</ul></details>`;
    });

    if (!boot.databases.some((db) => db.tables.length)) {
      html += `<div class="nav-empty">No tables yet.${isBuild() ? " Create one under Structure." : ""}</div>`;
    }

    nav.innerHTML = html;
  }

  function viewIcon(type) {
    return icon(["gallery", "kanban", "calendar", "form", "timeline", "graph", "survey"].includes(type) ? type : "grid");
  }

  function renderSheetChrome(bootstrap) {
    const views = bootstrap.views || [];
    const view = bootstrap.view;
    const filters = view.filters || [];
    const sorts = view.sorts || [];
    const groups = view.groups || [];
    const hidden = view.hidden_fields || [];
    const canBuild = !!bootstrap.canBuild;
    chrome.innerHTML = `
      <div class="views-bar">
        <div class="views-bar__tabs">
          ${views.map((v) => `
            <button type="button" class="view-tab${v.id === view.id ? " is-active" : ""}${v.is_personal ? " is-personal" : ""}" data-open-view="${v.id}">
              ${icon(v.type === "grid" ? "grid" : v.type)}
              ${esc(v.name)}
              ${v.is_personal ? icon("lock", 12) : ""}
            </button>
          `).join("")}
          ${canBuild ? `<button type="button" class="view-tab view-tab--add" data-menu="add-view">${icon("plus")}</button>
            <div class="menu" id="add-view" hidden>
              ${[["grid", "Grid"], ["gallery", "Gallery"], ["kanban", "Kanban"], ["calendar", "Calendar"], ["timeline", "Timeline"], ["graph", "Graph"], ["form", "Form"], ["survey", "Survey"]].map(([type, label]) =>
                `<button type="button" data-create-view="${type}">${icon(type === "grid" ? "grid" : type)} ${label}</button>`
              ).join("")}
              <div class="menu__sep"></div>
              <label class="check" style="margin:6px 8px"><input type="checkbox" id="create-personal"> Personal view</label>
            </div>` : ""}
        </div>
        <div class="views-bar__meta">
          <span>${esc(bootstrap.database?.name || "")}</span>
          <span class="dot">·</span>
          <strong>${esc(bootstrap.table.name)}</strong>
        </div>
      </div>
      ${view.type === "form" ? "" : `
      <div class="toolbar">
        <button type="button" class="tool${filters.length ? " is-on" : ""}" data-panel="filters">${icon("filter")} Filter${filters.length ? ` <em>${filters.length}</em>` : ""}</button>
        <button type="button" class="tool${sorts.length ? " is-on" : ""}" data-panel="sorts">${icon("sort")} Sort${sorts.length ? ` <em>${sorts.length}</em>` : ""}</button>
        ${view.type === "grid" ? `<button type="button" class="tool${groups.length ? " is-on" : ""}" data-panel="groups">${icon("hide")} Group</button>` : ""}
        <button type="button" class="tool" data-panel="hidden">${icon("hide")} Hide fields${hidden.length ? ` <em>${hidden.length}</em>` : ""}</button>
        <label class="toolbar__search">
          ${icon("search")}
          <input type="search" id="grid-search" value="${esc(bootstrap.search || current.search || "")}" placeholder="Search rows" autocomplete="off">
        </label>
      </div>`}
    `;
  }

  function renderPageChrome(title, detail) {
    chrome.innerHTML = `
      <div class="page-chrome">
        <div>
          <h1>${esc(title)}</h1>
          ${detail ? `<p>${esc(detail)}</p>` : ""}
        </div>
      </div>
    `;
    document.querySelectorAll(".panel").forEach((p) => { p.hidden = true; p.innerHTML = ""; });
    if (drawer) {
      drawer.hidden = true;
      drawer.innerHTML = "";
    }
  }

  function emptyState(title, body) {
    return `<div class="empty-state"><h2>${esc(title)}</h2><p>${esc(body)}</p></div>`;
  }

  function renderBoard(data) {
    renderPageChrome(data.dashboard?.name || "Dashboard", data.dashboard?.description || "Pinned numbers and recent rows.");
    if (!data.dashboard) {
      stage.innerHTML = emptyState("No dashboard yet", "A builder can pin counts and charts here.");
      return;
    }
    if (!data.widgets.length) {
      stage.innerHTML = emptyState("No widgets yet", "A builder can pin counts, charts, and recent rows from any table.");
      return;
    }
    stage.innerHTML = `<div class="page-body"><div class="widget-grid">${data.widgets.map((widget) => {
      const d = widget.data || {};
      const max = Math.max(1, ...(d.bars || []).map((b) => b.value));
      return `<article class="widget widget--${esc(widget.type)}">
        <header><h3>${esc(widget.title)}</h3></header>
        <p class="widget__value">${esc(d.value)}</p>
        <p class="widget__detail">${esc(d.detail)}</p>
        ${d.bars ? `<div class="widget__bars">${d.bars.map((bar) => `
          <div><span>${esc(bar.label)}</span><i style="width:${Math.round((bar.value / max) * 100)}%;background:${esc(bar.color)}"></i><em>${bar.value}</em></div>
        `).join("")}</div>` : ""}
        ${d.rows ? `<ul class="widget__list">${d.rows.map((row) => `
          <li><button type="button" data-nav="sheet" data-id="${row.table_id}">${esc(row.label)}</button></li>
        `).join("")}</ul>` : ""}
      </article>`;
    }).join("")}</div></div>`;
  }

  function renderStructure(data) {
    renderPageChrome("Structure", "Create child workspaces, databases, and tables. Open a sheet to add fields and views.");
    const children = (data.children || []).map((child) => `
      <li><button type="button" class="linkish" data-nav="workspace" data-id="${child.id}">${esc(child.name)}</button></li>
    `).join("") || "<li class='hint'>None yet</li>";
    stage.innerHTML = `<div class="page-body">
      <div class="split">
        <div>
          <article class="side-card" style="margin-bottom:16px">
            <h2>Child workspaces</h2>
            <ul class="plain-list">${children}</ul>
            <button type="button" class="btn btn--ghost" data-create-workspace data-parent="${boot.workspace.id}">Add child workspace</button>
          </article>
          <div class="card-grid">${(data.databases || []).map((db) => `
            <article class="entity-card">
              <div class="entity-card__icon entity-card__icon--database">${icon("database", 20)}</div>
              <div class="entity-card__body">
                <h3>${esc(db.name)}</h3>
                <p>${db.tables.length} ${db.tables.length === 1 ? "table" : "tables"}</p>
                <ul class="plain-list">${db.tables.map((t) => `
                  <li><button type="button" class="linkish" data-nav="sheet" data-id="${t.id}">${esc(t.name)}</button> · ${t.fields} fields</li>
                `).join("")}</ul>
              </div>
            </article>
          `).join("") || emptyState("No databases", "Create a database to get a first table.")}</div>
        </div>
        <form class="side-card" data-ajax="${boot.urls.databaseStore}">
          <h2>New database</h2>
          <label class="field"><span>Name</span><input name="name" required placeholder="e.g. Operations"></label>
          <button class="btn btn--primary" type="submit">Add database</button>
        </form>
      </div>
    </div>`;
  }

  function renderPeople(data) {
    renderPageChrome("People", "This workspace has its own roster. A parent can add people from here onto a child — they still need a seat on that child.");
    const children = (data.children || []).map((child) => `
      <article class="child-roster">
        <header>
          <h3>${esc(child.name)}</h3>
          <p>${child.members.length} ${child.members.length === 1 ? "person" : "people"} on this child</p>
        </header>
        <ul class="plain-list">${child.members.map((m) => `<li>${esc(m.name)} · ${esc(m.role_label)}</li>`).join("") || "<li class='hint'>Empty until someone is added.</li>"}</ul>
        ${child.candidates.length ? `
          <form class="child-roster__form" data-ajax="${boot.urls.memberChild}">
            <input type="hidden" name="child_id" value="${child.id}">
            <label class="field"><span>From this workspace</span>
              <select name="user_id">${child.candidates.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join("")}</select>
            </label>
            <label class="field"><span>Role on ${esc(child.name)}</span>
              <select name="role">${(child.invite_roles || data.invite_roles).map((r) => `<option value="${r}">${esc(r)}</option>`).join("")}</select>
            </label>
            <button class="btn btn--primary" type="submit">Add to ${esc(child.name)}</button>
          </form>
        ` : `<p class="hint">Everyone here already belongs to ${esc(child.name)}.</p>`}
      </article>
    `).join("");
    stage.innerHTML = `<div class="page-body"><div class="split">
      <div>
        <table class="data-table">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
          <tbody>${data.members.map((m) => `
            <tr>
              <td>${esc(m.name)}</td>
              <td>${esc(m.email)}</td>
              <td>
                <select data-member-role="${m.id}">
                  ${data.roles.map((r) => `<option value="${r.id}" ${r.id === m.role ? "selected" : ""}>${esc(r.label)}</option>`).join("")}
                </select>
              </td>
              <td>${m.id !== boot.user.id && m.role !== "owner" ? `<button type="button" class="btn btn--ghost" data-remove-member="${m.id}">Remove</button>` : ""}</td>
            </tr>
          `).join("")}</tbody>
        </table>
        ${children ? `<h2 class="section-title">Child workspaces</h2><div class="child-grid">${children}</div>` : ""}
      </div>
      <form class="side-card" data-ajax="${boot.urls.memberStore}">
        <h2>Invite to ${esc(boot.workspace.name)}</h2>
        <label class="field"><span>Email</span><input type="email" name="email" required></label>
        <label class="field"><span>Name</span><input name="name" placeholder="Optional if they already have an account"></label>
        <label class="field"><span>Role</span>
          <select name="role">${data.invite_roles.map((r) => `<option value="${r}">${esc(r)}</option>`).join("")}</select>
        </label>
        <button class="btn btn--primary" type="submit">Add person</button>
        <p class="hint">${data.seats} / ${data.seat_limit} seats on ${esc(data.plan_name)}. Access stops at this workspace unless you add them to a child.</p>
      </form>
    </div></div>`;
  }

  function renderLook() {
    const w = boot.workspace;
    renderPageChrome("Look", "Accent, sidebar, and logo belong to this workspace. Switching workspaces restores that workspace’s look and its own Build mode.");
    stage.innerHTML = `<div class="page-body">
      <form class="split look-form" id="look-form">
        <div class="side-card">
          <h2>This workspace</h2>
          <label class="field"><span>Name</span><input name="name" required value="${esc(w.name)}"></label>
          <label class="field"><span>Tagline</span><input name="tagline" maxlength="160" value="${esc(w.tagline || "")}" placeholder="Shown to people who belong here"></label>
          <div class="look-swatches">
            <label class="field"><span>Accent</span><input type="color" name="brand_color" value="${esc(w.brand_color || "#5190ef")}"></label>
            <label class="field"><span>Sidebar</span><input type="color" name="sidebar_color" value="${esc(w.sidebar_color || "#fafafa")}"></label>
          </div>
          <label class="field"><span>Logo</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif"></label>
          ${w.logo_url ? `<label class="check"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>` : ""}
          <button class="btn btn--primary" type="submit">Save look</button>
        </div>
        <div class="look-preview" id="look-preview">
          <div class="look-preview__bar">
            <strong>${esc(w.name)}</strong>
            <span>${esc(w.tagline || "Your colors, your logo.")}</span>
          </div>
          <p class="hint">Use and Build stay on this workspace. A child does not inherit these colors until someone with access sets them there.</p>
        </div>
      </form>
    </div>`;
    const form = document.getElementById("look-form");
    const preview = document.getElementById("look-preview");
    const syncPreview = () => {
      const accent = form.brand_color.value;
      const side = form.sidebar_color.value;
      preview.style.setProperty("--brand", accent);
      preview.style.setProperty("--sidebar-bg", side);
      preview.classList.toggle("is-dark", hexLuma(side) < 0.55);
    };
    form.brand_color.addEventListener("input", syncPreview);
    form.sidebar_color.addEventListener("input", syncPreview);
    syncPreview();
  }

  function renderAutomations(data) {
    renderPageChrome("Automations", `Workflows run when rows change. ${data.used} / ${data.limit} on ${data.plan_name}.`);
    const list = data.automations.length
      ? data.automations.map((auto) => `
        <article class="side-card" style="margin-bottom:12px">
          <header class="home__head" style="margin:0">
            <div>
              <h3 style="margin:0">${esc(auto.name)}</h3>
              <p>${esc(auto.table || "")} · ${esc(auto.trigger)} → ${esc(auto.action)}</p>
            </div>
            <div class="home__actions">
              <button type="button" class="btn btn--ghost" data-toggle-auto="${auto.id}" data-enabled="${auto.enabled ? 0 : 1}">${auto.enabled ? "Turn off" : "Turn on"}</button>
              <button type="button" class="btn btn--ghost" data-delete-auto="${auto.id}">Delete</button>
            </div>
          </header>
          ${auto.last_run ? `<p class="hint">Last run: ${esc(auto.last_run)}</p>` : ""}
        </article>`).join("")
      : emptyState("No automations", "Create a workflow to update fields, create rows, or notify the team.");
    stage.innerHTML = `<div class="page-body"><div class="split"><div>${list}</div>
      <form class="side-card" data-ajax="${boot.urls.automationStore}">
        <h2>New workflow</h2>
        <label class="field"><span>Name</span><input name="name" required placeholder="When a deal is won, notify builders"></label>
        <label class="field"><span>Table</span><select name="table_id" required>${data.tables.map((t) => `<option value="${t.id}">${esc(t.name)}</option>`).join("")}</select></label>
        <label class="field"><span>When</span>
          <select name="trigger">
            <option value="row_created">A row is created</option>
            <option value="row_updated">A row is updated</option>
            <option value="field_changed">A field changes</option>
          </select>
        </label>
        <label class="field"><span>Field that changed</span>
          <select name="trigger_field_id"><option value="">Any field</option>${data.fields.map((f) => `<option value="${f.id}">${esc(f.name)}</option>`).join("")}</select>
        </label>
        <label class="field"><span>Then</span>
          <select name="action">
            <option value="notify">Notify people</option>
            <option value="update_field">Update a field on this row</option>
            <option value="create_row">Create a row in another table</option>
          </select>
        </label>
        <label class="field"><span>Notification text</span><input name="notify_message" placeholder="A deal moved"></label>
        <button class="btn btn--primary" type="submit">Create automation</button>
      </form>
    </div></div>`;
  }

  function renderPlan(data) {
    renderPageChrome("Plan", "Choosing a plan updates limits immediately. No payment provider.");
    stage.innerHTML = `<div class="page-body"><div class="plan-grid">${data.plans.map((item) => `
      <article class="plan-card${item.id === data.current_id ? " is-current" : ""}">
        <h2>${esc(item.name)}</h2>
        <p class="plan-card__price">$${item.price_monthly}<small>/user/month</small></p>
        <p>${esc(item.tagline)}</p>
        <ul>
          <li>${item.members} seats</li>
          <li>${item.automations} automations</li>
          <li>${item.dashboards} dashboards</li>
          <li>${esc(item.views)} views</li>
        </ul>
        ${item.id === data.current_id
          ? `<span class="btn btn--ghost" aria-disabled="true">Current plan</span>`
          : `<button type="button" class="btn btn--primary" data-choose-plan="${item.id}">Switch to ${esc(item.name)}</button>`}
      </article>
    `).join("")}</div></div>`;
  }

  async function refreshBoot() {
    boot = await api(boot.urls.boot);
    renderMode();
    renderWorkspaceMenu();
    renderUserMenu();
    renderNav();
  }

  async function openSheet(tableId, viewId = null, search = "") {
    current = { kind: "sheet", tableId: Number(tableId), viewId, dashboardId: null, search };
    renderNav();
    setBusy(true);
    try {
      const url = new URL(`${boot.urls.sheet}/${tableId}`, location.origin);
      if (viewId) url.searchParams.set("view", viewId);
      if (search) url.searchParams.set("search", search);
      const data = await api(url.toString());
      current.viewId = data.bootstrap.view.id;
      renderSheetChrome(data.bootstrap);
      stage.className = `view-stage view-stage--${data.bootstrap.view.type} view-stage--${data.bootstrap.view.row_height || "small"}`;
      if (window.BaserowTable?.unmount) window.BaserowTable.unmount();
      window.BaserowTable.mount(data.bootstrap);
      bindSheetSearch(tableId);
    } catch (err) {
      toast(err.message || "Could not open sheet");
    } finally {
      setBusy(false);
      closeSidebar();
    }
  }

  function bindSheetSearch(tableId) {
    const input = document.getElementById("grid-search");
    if (!input) return;
    input.addEventListener("input", () => {
      current.search = input.value;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => openSheet(tableId, current.viewId, input.value), 220);
    });
    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        clearTimeout(searchTimer);
        openSheet(tableId, current.viewId, input.value);
      }
    });
  }

  async function openPanel(kind, id = null) {
    if (window.BaserowTable?.unmount) window.BaserowTable.unmount();
    current = { kind, tableId: null, viewId: null, dashboardId: id, search: "" };
    renderNav();
    setBusy(true);
    try {
      if (kind === "board") {
        const url = id ? `${boot.urls.board}/${id}` : boot.urls.board;
        renderBoard(await api(url));
      } else if (kind === "people") {
        renderPeople(await api(boot.urls.people));
      } else if (kind === "automations") {
        renderAutomations(await api(boot.urls.automations));
      } else if (kind === "plan") {
        renderPlan(await api(boot.urls.plan));
      } else if (kind === "structure") {
        renderStructure(await api(boot.urls.structure));
      } else if (kind === "look") {
        renderLook();
      }
    } catch (err) {
      toast(err.message || "Could not open this panel");
    } finally {
      setBusy(false);
      closeSidebar();
    }
  }

  async function setSurface(surface) {
    if (!boot.can_build) {
      toast("Build is for owners, admins, and builders.");
      return;
    }
    mode = surface === "builder" ? "build" : "use";
    boot.surface = surface;
    renderMode();
    renderNav();
    try {
      boot = await api(boot.urls.surface, {
        method: "POST",
        body: JSON.stringify({ surface }),
      });
      boot.surface = surface;
      renderMode();
      renderNav();
    } catch (err) {
      toast(err.message || "Could not save mode");
    }
    if (current.tableId) {
      await openSheet(current.tableId, current.viewId, current.search);
    } else if (mode === "build") {
      await openPanel("structure");
    } else if (boot.start.table_id) {
      await openSheet(boot.start.table_id);
    } else if (boot.start.dashboard_id) {
      await openPanel("board", boot.start.dashboard_id);
    }
  }

  async function openWorkspace(id) {
    if (Number(id) === Number(boot.workspace.id)) return;
    setBusy(true);
    try {
      boot = await api(boot.urls.open, {
        method: "POST",
        body: JSON.stringify({ workspace_id: Number(id) }),
      });
      mode = boot.can_build && boot.surface === "builder" ? "build" : "use";
      current = { kind: null, tableId: null, viewId: null, dashboardId: null, search: "" };
      renderMode();
      renderWorkspaceMenu();
      renderUserMenu();
      renderNav();
      if (window.BaserowTable?.unmount) window.BaserowTable.unmount();
      if (boot.start.table_id) await openSheet(boot.start.table_id);
      else if (boot.start.dashboard_id) await openPanel("board", boot.start.dashboard_id);
      else if (isBuild()) await openPanel("structure");
      else {
        renderPageChrome(boot.workspace.name, "This workspace has no tables yet.");
        stage.innerHTML = emptyState("Nothing to open", "Create a table in Build, or pick another workspace.");
      }
    } catch (err) {
      toast(err.message || "Could not open workspace");
    } finally {
      setBusy(false);
      closeSidebar();
    }
  }

  async function createWorkspace(parentId = null) {
    const name = prompt(parentId ? "Child workspace name" : "Workspace name");
    if (!name) return;
    try {
      const created = await api(boot.urls.workspaceStore, {
        method: "POST",
        body: JSON.stringify({ name, parent_id: parentId || undefined }),
      });
      toast("Workspace created");
      await openWorkspace(created.id);
    } catch (err) {
      toast(err.message || "Could not create workspace");
    }
  }

  window.BaserowShell = {
    reloadSheet({ viewId, search } = {}) {
      if (!current.tableId) return;
      return openSheet(current.tableId, viewId || current.viewId, search ?? current.search);
    },
    openSheet,
    refreshBoot,
  };

  document.addEventListener("click", async (e) => {
    const workspaceBtn = e.target.closest("[data-open-workspace]");
    if (workspaceBtn) {
      window.Baserow.closeMenus();
      await openWorkspace(workspaceBtn.dataset.openWorkspace);
      return;
    }
    const createWs = e.target.closest("[data-create-workspace]");
    if (createWs) {
      window.Baserow.closeMenus();
      await createWorkspace(createWs.dataset.parent || null);
      return;
    }
    const go = e.target.closest("[data-go]");
    if (go) {
      window.location.assign(go.dataset.go);
      return;
    }
    const mode = e.target.closest("#mode-switch [data-surface]");
    if (mode) {
      await setSurface(mode.dataset.surface);
      return;
    }
    const navBtn = e.target.closest("[data-nav]");
    if (navBtn) {
      const kind = navBtn.dataset.nav;
      if (kind === "sheet") await openSheet(navBtn.dataset.id);
      else if (kind === "board") await openPanel("board", Number(navBtn.dataset.id));
      else if (kind === "workspace") await openWorkspace(navBtn.dataset.id);
      else await openPanel(kind);
      return;
    }
    const viewTab = e.target.closest("[data-open-view]");
    if (viewTab && current.tableId) {
      await openSheet(current.tableId, Number(viewTab.dataset.openView), document.getElementById("grid-search")?.value || "");
      return;
    }
    const createView = e.target.closest("[data-create-view]");
    if (createView && current.tableId) {
      try {
        const created = await api(`/table/${current.tableId}/views`, {
          method: "POST",
          body: JSON.stringify({
            type: createView.dataset.createView,
            is_personal: !!document.getElementById("create-personal")?.checked,
          }),
        });
        await openSheet(current.tableId, created.id);
      } catch (err) {
        toast(err.message || "Could not create view");
      }
      return;
    }
    const choose = e.target.closest("[data-choose-plan]");
    if (choose) {
      try {
        const res = await api(boot.urls.planChoose, { method: "POST", body: JSON.stringify({ plan_id: Number(choose.dataset.choosePlan) }) });
        toast(res.status || "Plan updated");
        await refreshBoot();
        await openPanel("plan");
      } catch (err) {
        toast(err.message);
      }
      return;
    }
    const remove = e.target.closest("[data-remove-member]");
    if (remove && confirm("Remove this person?")) {
      try {
        await api(`/workspace/${boot.workspace.id}/people/${remove.dataset.removeMember}`, { method: "DELETE" });
        await openPanel("people");
      } catch (err) {
        toast(err.message);
      }
      return;
    }
    const toggle = e.target.closest("[data-toggle-auto]");
    if (toggle) {
      try {
        await api(`/workspace/${boot.workspace.id}/automations/${toggle.dataset.toggleAuto}`, {
          method: "PATCH",
          body: JSON.stringify({ enabled: toggle.dataset.enabled === "1" }),
        });
        await openPanel("automations");
      } catch (err) {
        toast(err.message);
      }
      return;
    }
    const delAuto = e.target.closest("[data-delete-auto]");
    if (delAuto && confirm("Delete this automation?")) {
      try {
        await api(`/workspace/${boot.workspace.id}/automations/${delAuto.dataset.deleteAuto}`, { method: "DELETE" });
        await openPanel("automations");
      } catch (err) {
        toast(err.message);
      }
    }
  });

  document.addEventListener("change", async (e) => {
    const role = e.target.closest("[data-member-role]");
    if (!role) return;
    try {
      await api(`/workspace/${boot.workspace.id}/people/${role.dataset.memberRole}`, {
        method: "PATCH",
        body: JSON.stringify({ role: role.value }),
      });
      toast("Role updated");
    } catch (err) {
      toast(err.message);
      openPanel("people");
    }
  });

  document.addEventListener("submit", async (e) => {
    const form = e.target.closest("[data-ajax]");
    if (!form) return;
    e.preventDefault();
    const fd = new FormData(form);
    const body = {};
    fd.forEach((value, key) => { body[key] = value; });
    try {
      const res = await api(form.getAttribute("data-ajax"), { method: "POST", body: JSON.stringify(body) });
      toast(res.status || "Saved");
      await refreshBoot();
      if (res.table_id) {
        await openSheet(res.table_id);
      } else if (current.kind === "structure") {
        await openPanel("structure");
      } else if (current.kind === "people") {
        await openPanel("people");
      } else if (current.kind === "automations") {
        await openPanel("automations");
      }
    } catch (err) {
      toast(err.message || "Could not save");
    }
  });

  document.addEventListener("submit", async (e) => {
    const form = e.target.closest("#look-form");
    if (!form) return;
    e.preventDefault();
    const fd = new FormData(form);
    try {
      const res = await fetch(boot.urls.look, {
        method: "POST",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": boot.urls.csrf, "X-Requested-With": "XMLHttpRequest" },
        body: fd,
      });
      const payload = await res.json();
      if (!res.ok) throw new Error(payload.message || Object.values(payload.errors || {})[0]?.[0] || "Could not save look");
      if (payload.boot) boot = payload.boot;
      toast(payload.status || "Look saved");
      renderMode();
      renderWorkspaceMenu();
      renderUserMenu();
      renderNav();
      renderLook();
    } catch (err) {
      toast(err.message || "Could not save look");
    }
  });

  document.getElementById("sidebar-search")?.addEventListener("input", renderNav);

  renderMode();
  renderWorkspaceMenu();
  renderUserMenu();
  renderNav();

  if (boot.start.kind === "sheet" && boot.start.table_id) {
    openSheet(boot.start.table_id);
  } else if (boot.start.dashboard_id) {
    openPanel("board", boot.start.dashboard_id);
  } else if (isBuild()) {
    openPanel("structure");
  } else {
    renderPageChrome(boot.workspace.name, "This workspace has no tables yet.");
    stage.innerHTML = emptyState("Nothing to open", "Ask a builder to create a table.");
  }
})();
