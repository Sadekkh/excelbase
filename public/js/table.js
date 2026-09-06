(() => {
  const bootEl = document.getElementById("table-bootstrap");
  if (!bootEl) return;
  const state = JSON.parse(bootEl.textContent);
  const api = window.Baserow.api;
  const toast = window.Baserow.toast;
  const stage = document.getElementById("view-stage");
  const drawer = document.getElementById("row-drawer");
  let selected = new Set();
  let calendarCursor = new Date();

  const ICONS = {
    text: "M6 6h12M12 6v13",
    "long-text": "M5 7h14M5 12h14M5 17h9",
    number: "M10 5 8 19M16 5l-2 14M5 9.5h15M4 14.5h15",
    boolean: "M4 4h16v16H4zM8 12.2 10.8 15 16 9",
    date: "M4 5h16v15H4zM4 10h16M8 3.5v3M16 3.5v3",
    "single-select": "M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z",
    "multiple-select": "M4 4h16v16H4zM8 12h8",
    url: "M9 15a4 4 0 0 1 0-6l2-2a4 4 0 0 1 6 6l-1.5 1.5M15 9a4 4 0 0 1 0 6l-2 2a4 4 0 0 1-6-6L8.5 9.5",
    email: "M3.5 6h17v12h-17zM4 8l8 6 8-6",
    phone: "M8 4.5h3l1 4-2 1.5a12 12 0 0 0 6 6L17.5 14l4 1v3A2 2 0 0 1 19.5 20 15.5 15.5 0 0 1 4 4.5 2 2 0 0 1 6 3h2z",
    rating: "M12 3.8 14.4 9l5.6.8-4 4 1 5.6L12 16.8 6.9 19.4l1-5.6-4-4L9.6 9 12 3.8z",
    created: "M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 4v4.2l3 1.8",
    modified: "M4 12a8 8 0 1 0 2.2-5.5M4 5v4h4",
    plus: "M12 6v12M6 12h12",
    x: "M6 6l12 12M18 6 6 18",
    check: "M5 12.5 10 17.5 19 7",
    expand: "M9 4H4v5M15 4h5v5M4 15v5h5M20 15v5h-5",
    trash: "M5 7h14M9 7V5h6v2M8 7l.8 12h6.4L16 7",
    drag: "M9 7h.01M15 7h.01M9 12h.01M15 12h.01M9 17h.01M15 17h.01",
    "chevron-left": "M14.5 7 9.5 12 14.5 17",
    "chevron-right": "M9.5 7 14.5 12 9.5 17",
  };

  function icon(name, size = 14) {
    const d = ICONS[name] || ICONS.text;
    return `<svg class="icon" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="${d}"/></svg>`;
  }

  function esc(s) {
    return String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  function colorOf(name) {
    return state.selectColors[name] || state.selectColors["light-gray"];
  }

  function fieldById(id) {
    return state.fields.find((f) => Number(f.id) === Number(id));
  }

  function visibleFields() {
    const hidden = new Set((state.view.hidden_fields || []).map(Number));
    return state.fields.filter((f) => !hidden.has(Number(f.id)));
  }

  function optionLabel(field, id) {
    return (field.options?.options || []).find((o) => String(o.id) === String(id));
  }

  function display(field, value) {
    if (field.type === "boolean") return value ? "true" : "";
    if (field.type === "single_select") {
      const opt = optionLabel(field, value);
      return opt ? opt.value : "";
    }
    if (field.type === "multiple_select") {
      return (value || []).map((id) => optionLabel(field, id)?.value).filter(Boolean).join(", ");
    }
    return value == null ? "" : String(value);
  }

  function pill(opt) {
    if (!opt) return "";
    const c = colorOf(opt.color);
    return `<span class="pill" style="background:${c.bg};color:${c.text}">${esc(opt.value)}</span>`;
  }

  function stars(n, max = 5) {
    const v = Number(n) || 0;
    return `<span class="stars">${"★".repeat(v)}<span style="color:#d7d8d9">${"★".repeat(Math.max(0, max - v))}</span></span>`;
  }

  function cellHTML(field, value) {
    if (field.type === "boolean") {
      return `<span class="bool ${value ? "is-on" : ""}">${value ? icon("check", 12) : ""}</span>`;
    }
    if (field.type === "single_select") return pill(optionLabel(field, value));
    if (field.type === "multiple_select") {
      return (value || []).map((id) => pill(optionLabel(field, id))).join(" ");
    }
    if (field.type === "rating") return stars(value, field.options?.max || 5);
    if (field.type === "url" && value) return `<a href="${esc(value)}" target="_blank" rel="noreferrer">${esc(value)}</a>`;
    if (field.type === "email" && value) return `<a href="mailto:${esc(value)}">${esc(value)}</a>`;
    return esc(value);
  }

  function empty(field, value) {
    if (value == null || value === "") return true;
    if (field.type === "multiple_select" && Array.isArray(value) && !value.length) return true;
    if (field.type === "boolean") return !value;
    return false;
  }

  function summarize(field, type) {
    const rows = state.rows;
    const values = rows.map((r) => r.values[field.id]);
    const count = rows.length;
    const emptyCount = values.filter((v) => empty(field, v)).length;
    const filled = count - emptyCount;
    const nums = values.map((v) => Number(v) || 0);
    if (type === "count") return String(count);
    if (type === "empty") return String(emptyCount);
    if (type === "filled") return String(filled);
    if (type === "unique") return String(new Set(values.map((v) => JSON.stringify(v))).size);
    if (type === "percent_empty") return count ? Math.round((emptyCount / count) * 100) + "%" : "—";
    if (type === "percent_filled") return count ? Math.round((filled / count) * 100) + "%" : "—";
    if (type === "sum") return String(nums.reduce((a, b) => a + b, 0));
    if (type === "average") return filled ? String(Math.round((nums.reduce((a, b) => a + b, 0) / filled) * 100) / 100) : "—";
    if (type === "min") return filled ? String(Math.min(...nums)) : "—";
    if (type === "max") return filled ? String(Math.max(...nums)) : "—";
    return "";
  }

  function groupedRows(rows) {
    const groups = state.view.groups || [];
    if (!groups.length) {
      return rows.map((row, index) => ({ kind: "row", row, index: index + 1 }));
    }
    const field = fieldById(groups[0].field_id);
    const buckets = new Map();
    rows.forEach((row) => {
      const label = field ? display(field, row.values[field.id]) || "No value" : "All";
      if (!buckets.has(label)) buckets.set(label, []);
      buckets.get(label).push(row);
    });
    const out = [];
    let index = 1;
    buckets.forEach((list, label) => {
      out.push({ kind: "group", label, count: list.length });
      list.forEach((row) => out.push({ kind: "row", row, index: index++ }));
    });
    return out;
  }

  function render() {
    selected = new Set([...selected].filter((id) => state.rows.some((r) => r.id === id)));
    if (state.view.type === "gallery") return renderGallery();
    if (state.view.type === "kanban") return renderKanban();
    if (state.view.type === "calendar") return renderCalendar();
    if (state.view.type === "form") return renderForm();
    return renderGrid();
  }

  function renderGrid() {
    const fields = visibleFields();
    const summaries = state.view.field_options?.summaries || {};
    const rows = state.rows;
    stage.innerHTML = `
      <div class="grid">
        <div class="grid__scroll">
          <table class="grid__table">
            <thead>
              <tr>
                <th class="grid__gutter"><div class="grid__gutter-inner"></div></th>
                ${fields.map((f) => `
                  <th style="width:${f.width}px;min-width:${f.width}px" data-field-id="${f.id}">
                    <div class="field-head" data-field-menu="${f.id}">
                      <span class="field-head__icon">${icon(f.icon)}</span>
                      <span class="field-head__name">${esc(f.name)}</span>
                    </div>
                    <div class="col-resizer" data-resize="${f.id}"></div>
                  </th>`).join("")}
                <th style="min-width:140px"><button class="add-field" data-add-field>${icon("plus")} Add field</button></th>
              </tr>
            </thead>
            <tbody>
              ${groupedRows(rows).map((entry, i) => entry.kind === "group" ? `
                <tr class="grid__row"><td class="grid__gutter"></td><td colspan="${fields.length + 1}">
                  <div class="cell" style="font-weight:600;background:var(--neutral-25)">${esc(entry.label)} · ${entry.count}</div>
                </td></tr>` : `
                <tr class="grid__row ${selected.has(entry.row.id) ? "is-selected" : ""}" data-row-id="${entry.row.id}">
                  <td class="grid__gutter">
                    <div class="grid__gutter-inner">
                      <input type="checkbox" class="grid__check" data-select-row="${entry.row.id}" ${selected.has(entry.row.id) ? "checked" : ""}>
                      <span>${entry.index}</span>
                      <button type="button" data-open-row="${entry.row.id}" title="Expand">${icon("expand", 13)}</button>
                    </div>
                  </td>
                  ${fields.map((f) => `
                    <td style="width:${f.width}px;min-width:${f.width}px">
                      <div class="cell ${f.primary ? "is-primary" : ""} ${empty(f, entry.row.values[f.id]) ? "cell--empty" : ""}" data-edit="${entry.row.id}:${f.id}">
                        ${cellHTML(f, entry.row.values[f.id])}
                      </div>
                    </td>`).join("")}
                  <td></td>
                </tr>`).join("")}
              <tr>
                <td class="grid__gutter"></td>
                <td colspan="${fields.length + 1}"><button class="add-row" data-add-row>${icon("plus")} New row</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="grid__foot">
          <span>${rows.length} ${rows.length === 1 ? "row" : "rows"}</span>
          ${fields.map((f) => {
            const current = summaries[f.id];
            return `<button type="button" data-summary="${f.id}">${esc(f.name)}: ${current ? esc(summarize(f, current) + " · " + (f.summaries[current] || current)) : "Summary"}</button>`;
          }).join("")}
        </div>
        ${selected.size ? `<div class="bulk-bar">${selected.size} selected <button data-dup-selected>Duplicate</button><button data-del-selected>Delete</button></div>` : ""}
      </div>`;
    bindResize();
  }

  function renderGallery() {
    const fields = visibleFields();
    const primary = fields.find((f) => f.primary) || fields[0];
    stage.innerHTML = `<div class="gallery">
      ${state.rows.map((row) => `
        <article class="card" data-open-row="${row.id}">
          <h3>${esc(display(primary, row.values[primary?.id])) || "Untitled"}</h3>
          <dl>${fields.filter((f) => !f.primary).slice(0, 5).map((f) => `
            <div><dt>${esc(f.name)}</dt><dd>${cellHTML(f, row.values[f.id]) || "—"}</dd></div>`).join("")}</dl>
        </article>`).join("")}
      <button class="card gallery__add" data-add-row>${icon("plus")} New row</button>
    </div>`;
  }

  function renderKanban() {
    const stack = fieldById(state.view.kanban_field_id) || state.fields.find((f) => f.type === "single_select");
    const primary = state.fields.find((f) => f.primary);
    if (!stack) {
      stage.innerHTML = `<div class="empty-state" style="margin:24px"><h2>Kanban needs a single select field</h2><p>Add a single select field to stack cards.</p><button class="btn btn--primary" data-add-field>Add field</button></div>`;
      return;
    }
    const options = stack.options?.options || [];
    const cols = [...options, { id: "", value: "No value", color: "light-gray" }];
    stage.innerHTML = `<div class="kanban">${cols.map((opt) => {
      const cards = state.rows.filter((r) => String(r.values[stack.id] || "") === String(opt.id));
      return `<section class="kanban__col" data-stack="${opt.id}">
        <h3>${pill(opt.id ? opt : { value: "No value", color: "light-gray" })} <span>${cards.length}</span></h3>
        ${cards.map((row) => `<article class="kanban-card" draggable="true" data-row-id="${row.id}" data-open-row="${row.id}">
          <strong>${esc(display(primary, row.values[primary.id])) || "Untitled"}</strong>
        </article>`).join("")}
        <button class="add-row" data-add-row data-preset='${JSON.stringify({ [stack.id]: opt.id || null })}'>${icon("plus")} New</button>
      </section>`;
    }).join("")}</div>`;
    bindKanban(stack);
  }

  function bindKanban(stack) {
    let dragId = null;
    stage.querySelectorAll(".kanban-card").forEach((card) => {
      card.addEventListener("dragstart", (e) => {
        dragId = Number(card.dataset.rowId);
        card.classList.add("is-dragging");
        e.dataTransfer.effectAllowed = "move";
      });
      card.addEventListener("dragend", () => card.classList.remove("is-dragging"));
    });
    stage.querySelectorAll(".kanban__col").forEach((col) => {
      col.addEventListener("dragover", (e) => e.preventDefault());
      col.addEventListener("drop", async (e) => {
        e.preventDefault();
        if (!dragId) return;
        const value = col.dataset.stack || null;
        await updateRow(dragId, { [stack.id]: value });
      });
    });
  }

  function renderCalendar() {
    const dateField = state.fields.find((f) => f.type === "date") || fieldById(state.view.kanban_field_id);
    const primary = state.fields.find((f) => f.primary);
    const year = calendarCursor.getFullYear();
    const month = calendarCursor.getMonth();
    const first = new Date(year, month, 1);
    const start = new Date(first);
    start.setDate(1 - ((first.getDay() + 6) % 7));
    const days = [];
    for (let i = 0; i < 42; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      days.push(d);
    }
    const title = calendarCursor.toLocaleString("en", { month: "long", year: "numeric" });
    stage.innerHTML = `<div class="calendar">
      <div class="calendar__head">
        <button class="icon-btn" data-cal="-1">${icon("chevron-left")}</button>
        <h2>${title}</h2>
        <button class="icon-btn" data-cal="1">${icon("chevron-right")}</button>
      </div>
      <div class="calendar__grid">
        ${["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].map((d) => `<div class="calendar__dow">${d}</div>`).join("")}
        ${days.map((d) => {
          const key = d.toISOString().slice(0, 10);
          const events = dateField ? state.rows.filter((r) => String(r.values[dateField.id] || "").slice(0, 10) === key) : [];
          return `<div class="calendar__day ${d.getMonth() !== month ? "is-out" : ""}">
            <div class="calendar__num">${d.getDate()}</div>
            ${events.map((r) => `<div class="calendar__event" data-open-row="${r.id}">${esc(display(primary, r.values[primary.id]) || "Untitled")}</div>`).join("")}
          </div>`;
        }).join("")}
      </div>
    </div>`;
    stage.querySelector("[data-cal='-1']").onclick = () => { calendarCursor.setMonth(calendarCursor.getMonth() - 1); renderCalendar(); };
    stage.querySelector("[data-cal='1']").onclick = () => { calendarCursor.setMonth(calendarCursor.getMonth() + 1); renderCalendar(); };
  }

  function renderForm() {
    const cfg = state.view.form_config || {};
    const hidden = new Set((state.view.hidden_fields || []).map(Number));
    const fields = state.fields.filter((f) => !f.read_only && !hidden.has(Number(f.id)));
    const publicUrl = state.view.public && state.view.public_slug
      ? `${location.origin}/form/${state.view.public_slug}`
      : "";
    stage.innerHTML = `<div class="form-builder">
      <div class="form-preview">
        <div class="public-form">
          <div class="public-form__cover" style="background:${esc(cfg.cover || "#5190ef")}"></div>
          <div class="public-form__card">
            <div class="public-form__brand">${icon("text", 18)} Baserow</div>
            <h1>${esc(cfg.title || state.table.name)}</h1>
            <p class="public-form__desc">${esc(cfg.description || "")}</p>
            <div class="public-form__fields">
              ${fields.map((f) => `<label class="field"><span>${esc(f.name)}</span><input disabled placeholder="${esc(f.label)}"></label>`).join("")}
              <button class="btn btn--primary btn--block" type="button">${esc(cfg.submit_text || "Submit")}</button>
            </div>
          </div>
        </div>
      </div>
      <div class="form-settings">
        <h3>Form settings</h3>
        <label class="field"><span>Title</span><input id="form-title" value="${esc(cfg.title || state.table.name)}"></label>
        <label class="field"><span>Description</span><textarea id="form-desc" rows="3">${esc(cfg.description || "")}</textarea></label>
        <label class="field"><span>Submit button</span><input id="form-submit" value="${esc(cfg.submit_text || "Submit")}"></label>
        <label class="field"><span>Success message</span><input id="form-success" value="${esc(cfg.success_message || "Thank you for submitting!")}"></label>
        <label class="field"><span>Cover color</span><input id="form-cover" type="color" value="${cfg.cover || "#5190ef"}"></label>
        <h3>Visible fields</h3>
        ${state.fields.filter((f) => !f.read_only).map((f) => `
          <label class="check"><input type="checkbox" data-form-field="${f.id}" ${hidden.has(Number(f.id)) ? "" : "checked"}> ${esc(f.name)}</label>
        `).join("")}
        <button class="btn btn--primary" data-save-form>Save form</button>
        <button class="btn btn--ghost" data-share-view>${publicUrl ? "Copy public link" : "Enable public link"}</button>
        ${publicUrl ? `<label class="field"><span>Public URL</span><input readonly value="${esc(publicUrl)}"></label>` : ""}
      </div>
    </div>`;
  }

  function bindResize() {
    stage.querySelectorAll("[data-resize]").forEach((handle) => {
      handle.addEventListener("mousedown", (e) => {
        e.preventDefault();
        const id = Number(handle.dataset.resize);
        const field = fieldById(id);
        const startX = e.clientX;
        const startW = field.width;
        handle.classList.add("is-on");
        const move = (ev) => {
          field.width = Math.max(80, Math.min(800, startW + ev.clientX - startX));
          const th = handle.parentElement;
          th.style.width = field.width + "px";
          th.style.minWidth = field.width + "px";
        };
        const up = async () => {
          document.removeEventListener("mousemove", move);
          document.removeEventListener("mouseup", up);
          handle.classList.remove("is-on");
          await api(`${state.routes.field}/${id}`, { method: "PATCH", body: JSON.stringify({ width: field.width }) });
        };
        document.addEventListener("mousemove", move);
        document.addEventListener("mouseup", up);
      });
    });
  }

  async function updateRow(id, values) {
    const row = state.rows.find((r) => r.id === id);
    Object.assign(row.values, values);
    render();
    const updated = await api(`${state.routes.row}/${id}`, { method: "PATCH", body: JSON.stringify({ values }) });
    Object.assign(row, updated);
    render();
  }

  async function addRow(preset = {}) {
    const created = await api(state.urls.rows, { method: "POST", body: JSON.stringify({ values: preset }) });
    state.rows.push(created);
    render();
    toast("Row created");
    return created;
  }

  async function deleteRows(ids) {
    await api(state.urls.rowsDeleteMany, { method: "DELETE", body: JSON.stringify({ ids }) });
    state.rows = state.rows.filter((r) => !ids.includes(r.id));
    selected.clear();
    render();
    toast(ids.length === 1 ? "Row deleted" : `${ids.length} rows deleted`);
  }

  async function saveView(patch) {
    Object.assign(state.view, patch);
    const updated = await api(state.urls.view, { method: "PATCH", body: JSON.stringify(patch) });
    Object.assign(state.view, updated);
  }

  function reload() {
    const url = new URL(state.urls.table, location.origin);
    url.searchParams.set("view", state.view.id);
    const q = document.getElementById("grid-search")?.value;
    if (q) url.searchParams.set("search", q);
    location.href = url.toString();
  }

  function startEdit(rowId, fieldId, cell) {
    const field = fieldById(fieldId);
    const row = state.rows.find((r) => r.id === rowId);
    if (!field || !row || field.read_only) return;
    if (field.type === "boolean") {
      updateRow(rowId, { [field.id]: !row.values[field.id] });
      return;
    }
    const value = row.values[field.id];
    cell.classList.add("is-editing");
    let input;
    if (field.type === "long_text") {
      input = document.createElement("textarea");
      input.value = value || "";
    } else if (field.type === "date") {
      input = document.createElement("input");
      input.type = field.options?.include_time ? "datetime-local" : "date";
      input.value = (value || "").slice(0, 16);
    } else if (field.type === "number" || field.type === "rating") {
      input = document.createElement("input");
      input.type = "number";
      if (field.type === "rating") {
        input.min = 0;
        input.max = field.options?.max || 5;
      }
      input.value = value ?? "";
    } else if (field.type === "single_select") {
      input = document.createElement("select");
      input.innerHTML = `<option value=""></option>` + (field.options?.options || []).map((o) =>
        `<option value="${esc(o.id)}" ${String(o.id) === String(value) ? "selected" : ""}>${esc(o.value)}</option>`
      ).join("");
    } else {
      input = document.createElement("input");
      input.type = field.type === "email" ? "email" : field.type === "url" ? "url" : "text";
      input.value = value || "";
    }
    cell.innerHTML = "";
    cell.appendChild(input);
    input.focus();
    const commit = async () => {
      let next = input.value;
      if (field.type === "number" || field.type === "rating") next = next === "" ? null : Number(next);
      if (field.type === "single_select") next = next || null;
      await updateRow(rowId, { [field.id]: next });
    };
    input.addEventListener("blur", commit);
    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && field.type !== "long_text") { e.preventDefault(); input.blur(); }
      if (e.key === "Escape") render();
    });
  }

  function openDrawer(rowId) {
    const row = state.rows.find((r) => r.id === rowId);
    if (!row) return;
    drawer.hidden = false;
    drawer.innerHTML = `
      <div class="row-drawer__head">
        <strong>Row #${row.id}</strong>
        <div>
          <button class="icon-btn" data-dup-row="${row.id}">${icon("plus")}</button>
          <button class="icon-btn" data-del-row="${row.id}">${icon("trash")}</button>
          <button class="icon-btn" data-close-drawer>${icon("x")}</button>
        </div>
      </div>
      <div class="row-drawer__body">
        ${state.fields.map((f) => `
          <label class="field">
            <span>${esc(f.name)}</span>
            ${drawerControl(f, row.values[f.id], row.id)}
          </label>`).join("")}
      </div>`;
  }

  function drawerControl(field, value, rowId) {
    if (field.read_only) return `<input disabled value="${esc(value || "")}">`;
    if (field.type === "long_text") return `<textarea data-drawer-field="${field.id}" data-row="${rowId}" rows="4">${esc(value || "")}</textarea>`;
    if (field.type === "boolean") return `<label class="check"><input type="checkbox" data-drawer-field="${field.id}" data-row="${rowId}" ${value ? "checked" : ""}> Yes</label>`;
    if (field.type === "single_select") {
      return `<select data-drawer-field="${field.id}" data-row="${rowId}"><option value=""></option>${(field.options?.options || []).map((o) =>
        `<option value="${esc(o.id)}" ${String(o.id) === String(value) ? "selected" : ""}>${esc(o.value)}</option>`).join("")}</select>`;
    }
    if (field.type === "multiple_select") {
      const set = new Set((value || []).map(String));
      return `<div class="check-list">${(field.options?.options || []).map((o) =>
        `<label class="check"><input type="checkbox" data-multi="${field.id}" data-row="${rowId}" value="${esc(o.id)}" ${set.has(String(o.id)) ? "checked" : ""}> ${esc(o.value)}</label>`).join("")}</div>`;
    }
    if (field.type === "date") return `<input type="${field.options?.include_time ? "datetime-local" : "date"}" data-drawer-field="${field.id}" data-row="${rowId}" value="${esc((value || "").slice(0, 16))}">`;
    if (field.type === "rating" || field.type === "number") return `<input type="number" data-drawer-field="${field.id}" data-row="${rowId}" value="${esc(value ?? "")}">`;
    return `<input data-drawer-field="${field.id}" data-row="${rowId}" value="${esc(value || "")}">`;
  }

  function fieldModal(existing) {
    const types = Object.entries(state.fieldTypes);
    const root = document.getElementById("modal-root");
    const selectedType = existing?.type || "text";
    root.innerHTML = `<div class="modal" id="field-modal">
      <div class="modal__backdrop" data-close-field></div>
      <form class="modal__dialog" id="field-form">
        <header class="modal__head"><h2>${existing ? "Edit field" : "Create field"}</h2>
          <button type="button" class="icon-btn" data-close-field>${icon("x")}</button></header>
        <div class="modal__body">
          <label class="field"><span>Name</span><input name="name" required value="${esc(existing?.name || "")}" placeholder="Field name"></label>
          <div class="type-grid">
            ${types.map(([key, meta]) => `
              <label class="type-opt ${key === selectedType ? "is-on" : ""}">
                <input type="radio" name="type" value="${key}" ${key === selectedType ? "checked" : ""} hidden>
                ${icon(meta.icon)} ${esc(meta.label)}
              </label>`).join("")}
          </div>
          <div id="field-options"></div>
        </div>
        <footer class="modal__foot">
          ${existing && !existing.primary ? `<button type="button" class="btn btn--ghost" style="margin-right:auto;color:#ff5a44" data-delete-field="${existing.id}">Delete</button>` : ""}
          <button type="button" class="btn btn--ghost" data-close-field>Cancel</button>
          <button type="submit" class="btn btn--primary">Save</button>
        </footer>
      </form>
    </div>`;
    const form = document.getElementById("field-form");
    const optionsBox = document.getElementById("field-options");
    function paintOptions(type, current) {
      if (type === "single_select" || type === "multiple_select") {
        const opts = current?.options?.options || [{ value: "Option 1", color: "blue" }, { value: "Option 2", color: "green" }];
        optionsBox.innerHTML = `<div class="field"><span>Options</span><div id="opt-list">${opts.map(optRow).join("")}</div>
          <button type="button" class="panel-add" id="add-opt">Add option</button></div>`;
        document.getElementById("add-opt").onclick = () => {
          document.getElementById("opt-list").insertAdjacentHTML("beforeend", optRow({ value: "", color: "light-gray" }));
        };
      } else if (type === "number") {
        optionsBox.innerHTML = `<label class="field"><span>Decimal places</span><input name="decimal_places" type="number" min="0" max="10" value="${current?.options?.decimal_places ?? 0}"></label>
          <label class="field"><span>Prefix</span><input name="prefix" value="${esc(current?.options?.prefix || "")}"></label>
          <label class="field"><span>Suffix</span><input name="suffix" value="${esc(current?.options?.suffix || "")}"></label>`;
      } else if (type === "rating") {
        optionsBox.innerHTML = `<label class="field"><span>Maximum</span><input name="max" type="number" min="1" max="10" value="${current?.options?.max ?? 5}"></label>`;
      } else if (type === "date") {
        optionsBox.innerHTML = `<label class="check"><input type="checkbox" name="include_time" ${current?.options?.include_time ? "checked" : ""}> Include time</label>`;
      } else {
        optionsBox.innerHTML = "";
      }
    }
    function optRow(opt) {
      const colors = Object.keys(state.selectColors);
      return `<div class="option-row">
        <select name="opt_color[]">${colors.map((c) => `<option value="${c}" ${c === opt.color ? "selected" : ""}>${c}</option>`).join("")}</select>
        <input name="opt_value[]" value="${esc(opt.value || "")}" placeholder="Option">
        <input type="hidden" name="opt_id[]" value="${esc(opt.id || "")}">
      </div>`;
    }
    paintOptions(selectedType, existing);
    form.querySelectorAll(".type-opt").forEach((el) => {
      el.addEventListener("click", () => {
        form.querySelectorAll(".type-opt").forEach((x) => x.classList.remove("is-on"));
        el.classList.add("is-on");
        el.querySelector("input").checked = true;
        paintOptions(el.querySelector("input").value, existing);
      });
    });
    root.querySelectorAll("[data-close-field]").forEach((b) => b.onclick = () => { root.innerHTML = ""; });
    const del = root.querySelector("[data-delete-field]");
    if (del) {
      del.onclick = async () => {
        if (!confirm("Delete this field and its values?")) return;
        await api(`${state.routes.field}/${existing.id}`, { method: "DELETE" });
        state.fields = state.fields.filter((f) => f.id !== existing.id);
        root.innerHTML = "";
        render();
        toast("Field deleted");
      };
    }
    form.onsubmit = async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const type = fd.get("type");
      const payload = { name: fd.get("name"), type, options: {} };
      if (type === "single_select" || type === "multiple_select") {
        const values = fd.getAll("opt_value[]");
        const colors = fd.getAll("opt_color[]");
        const ids = fd.getAll("opt_id[]");
        payload.options = {
          options: values.map((value, i) => ({
            id: ids[i] || crypto.randomUUID(),
            value,
            color: colors[i],
          })).filter((o) => o.value),
        };
      } else if (type === "number") {
        payload.options = { decimal_places: Number(fd.get("decimal_places") || 0), prefix: fd.get("prefix") || "", suffix: fd.get("suffix") || "" };
      } else if (type === "rating") {
        payload.options = { max: Number(fd.get("max") || 5), style: "star" };
      } else if (type === "date") {
        payload.options = { include_time: fd.has("include_time"), format: "ISO" };
      }
      if (existing) {
        const updated = await api(`${state.routes.field}/${existing.id}`, { method: "PATCH", body: JSON.stringify(payload) });
        Object.assign(existing, updated);
      } else {
        const created = await api(state.urls.fields, { method: "POST", body: JSON.stringify(payload) });
        state.fields.push(created);
      }
      root.innerHTML = "";
      render();
      toast(existing ? "Field updated" : "Field created");
    };
  }

  function renderPanel(name) {
    const box = document.getElementById(`panel-${name}`);
    document.querySelectorAll(".panel").forEach((p) => { if (p !== box) p.hidden = true; });
    box.hidden = !box.hidden;
    if (box.hidden) return;
    if (name === "filters") {
      const items = state.view.filters || [];
      box.innerHTML = items.map((f, i) => {
        const field = fieldById(f.field_id) || state.fields[0];
        const ops = field?.operators || {};
        return `<div class="panel-row">
          <select data-filter="${i}" data-key="field_id">${state.fields.map((x) => `<option value="${x.id}" ${x.id == f.field_id ? "selected" : ""}>${esc(x.name)}</option>`).join("")}</select>
          <select data-filter="${i}" data-key="operator">${Object.entries(ops).map(([k, l]) => `<option value="${k}" ${k === f.operator ? "selected" : ""}>${esc(l)}</option>`).join("")}</select>
          <input data-filter="${i}" data-key="value" value="${esc(f.value || "")}">
          <button data-filter-del="${i}">${icon("x")}</button>
        </div>`;
      }).join("") + `<button class="panel-add" data-filter-add>Add filter</button>`;
    }
    if (name === "sorts") {
      const items = state.view.sorts || [];
      box.innerHTML = items.map((s, i) => `<div class="panel-row">
        <select data-sort="${i}" data-key="field_id">${state.fields.map((x) => `<option value="${x.id}" ${x.id == s.field_id ? "selected" : ""}>${esc(x.name)}</option>`).join("")}</select>
        <select data-sort="${i}" data-key="direction">
          <option value="asc" ${s.direction === "asc" ? "selected" : ""}>Ascending</option>
          <option value="desc" ${s.direction === "desc" ? "selected" : ""}>Descending</option>
        </select>
        <button data-sort-del="${i}">${icon("x")}</button>
      </div>`).join("") + `<button class="panel-add" data-sort-add>Add sort</button>`;
    }
    if (name === "groups") {
      const items = state.view.groups || [];
      box.innerHTML = items.map((g, i) => `<div class="panel-row">
        <select data-group="${i}" data-key="field_id">${state.fields.map((x) => `<option value="${x.id}" ${x.id == g.field_id ? "selected" : ""}>${esc(x.name)}</option>`).join("")}</select>
        <button data-group-del="${i}">${icon("x")}</button>
      </div>`).join("") + `<button class="panel-add" data-group-add>Add group</button>`;
    }
    if (name === "hidden") {
      const hidden = new Set((state.view.hidden_fields || []).map(Number));
      box.innerHTML = state.fields.map((f) => `<label class="check" style="margin:6px 12px 6px 0">
        <input type="checkbox" data-hide="${f.id}" ${hidden.has(Number(f.id)) ? "" : "checked"} ${f.primary ? "disabled" : ""}> ${esc(f.name)}
      </label>`).join("");
    }
  }

  async function persistFiltersSorts() {
    await saveView({
      filters: state.view.filters,
      sorts: state.view.sorts,
      groups: state.view.groups,
      hidden_fields: state.view.hidden_fields,
    });
    reload();
  }

  document.addEventListener("click", async (e) => {
    const t = e.target.closest("[data-add-field]");
    if (t) { fieldModal(); return; }
    const fh = e.target.closest("[data-field-menu]");
    if (fh) { fieldModal(fieldById(fh.dataset.fieldMenu)); return; }
    const addRowBtn = e.target.closest("[data-add-row]");
    if (addRowBtn) {
      let preset = {};
      try { preset = JSON.parse(addRowBtn.dataset.preset || "{}"); } catch (_) {}
      await addRow(preset);
      return;
    }
    const edit = e.target.closest("[data-edit]");
    if (edit && !edit.classList.contains("is-editing")) {
      const [rowId, fieldId] = edit.dataset.edit.split(":");
      startEdit(Number(rowId), Number(fieldId), edit);
      return;
    }
    const open = e.target.closest("[data-open-row]");
    if (open) { openDrawer(Number(open.dataset.openRow)); return; }
    if (e.target.closest("[data-close-drawer]")) { drawer.hidden = true; return; }
    const panel = e.target.closest("[data-panel]");
    if (panel) { renderPanel(panel.dataset.panel); return; }
    const height = e.target.closest("[data-row-height]");
    if (height) {
      await saveView({ row_height: height.dataset.rowHeight });
      document.getElementById("view-stage").className = `view-stage view-stage--${state.view.type} view-stage--${state.view.row_height}`;
      window.Baserow.closeMenus();
      render();
      return;
    }
    const createView = e.target.closest("[data-create-view]");
    if (createView) {
      const form = document.createElement("form");
      form.method = "post";
      form.action = state.urls.views;
      form.innerHTML = `<input name="_token" value="${state.csrf}"><input name="type" value="${createView.dataset.createView}">`;
      document.body.appendChild(form);
      form.submit();
      return;
    }
    const filterAdd = e.target.closest("[data-filter-add]");
    if (filterAdd) {
      state.view.filters = state.view.filters || [];
      state.view.filters.push({ field_id: state.fields[0].id, operator: "contains", value: "" });
      renderPanel("filters");
      return;
    }
    const filterDel = e.target.closest("[data-filter-del]");
    if (filterDel) {
      state.view.filters.splice(Number(filterDel.dataset.filterDel), 1);
      persistFiltersSorts();
      return;
    }
    const sortAdd = e.target.closest("[data-sort-add]");
    if (sortAdd) {
      state.view.sorts = state.view.sorts || [];
      state.view.sorts.push({ field_id: state.fields[0].id, direction: "asc" });
      persistFiltersSorts();
      return;
    }
    const sortDel = e.target.closest("[data-sort-del]");
    if (sortDel) {
      state.view.sorts.splice(Number(sortDel.dataset.sortDel), 1);
      persistFiltersSorts();
      return;
    }
    const groupAdd = e.target.closest("[data-group-add]");
    if (groupAdd) {
      state.view.groups = state.view.groups || [];
      state.view.groups.push({ field_id: state.fields[0].id });
      persistFiltersSorts();
      return;
    }
    const groupDel = e.target.closest("[data-group-del]");
    if (groupDel) {
      state.view.groups.splice(Number(groupDel.dataset.groupDel), 1);
      persistFiltersSorts();
      return;
    }
    const summary = e.target.closest("[data-summary]");
    if (summary) {
      const field = fieldById(summary.dataset.summary);
      const keys = Object.keys(field.summaries);
      const current = state.view.field_options?.summaries?.[field.id];
      const next = keys[(keys.indexOf(current) + 1) % keys.length];
      state.view.field_options = state.view.field_options || {};
      state.view.field_options.summaries = state.view.field_options.summaries || {};
      state.view.field_options.summaries[field.id] = next;
      await saveView({ field_options: state.view.field_options });
      render();
      return;
    }
    const share = e.target.closest("[data-share-view]");
    if (share) {
      const updated = await api(state.urls.view, { method: "PATCH", body: JSON.stringify({ public: true }) });
      Object.assign(state.view, updated);
      const url = `${location.origin}/form/${state.view.public_slug}`;
      if (state.view.type === "form") {
        try { await navigator.clipboard.writeText(url); toast("Public form link copied"); } catch (_) { toast(url); }
        render();
      } else {
        toast("Public sharing is available on form views. Duplicate this as a form to share.");
      }
      return;
    }
    const saveForm = e.target.closest("[data-save-form]");
    if (saveForm) {
      const hidden = [...document.querySelectorAll("[data-form-field]")].filter((c) => !c.checked).map((c) => Number(c.dataset.formField));
      await saveView({
        hidden_fields: hidden,
        form_config: {
          title: document.getElementById("form-title").value,
          description: document.getElementById("form-desc").value,
          submit_text: document.getElementById("form-submit").value,
          success_message: document.getElementById("form-success").value,
          cover: document.getElementById("form-cover").value,
        },
      });
      toast("Form saved");
      render();
      return;
    }
    const delSel = e.target.closest("[data-del-selected]");
    if (delSel) { await deleteRows([...selected]); return; }
    const dupSel = e.target.closest("[data-dup-selected]");
    if (dupSel) {
      for (const id of [...selected]) {
        const copy = await api(`${state.routes.row}/${id}/duplicate`, { method: "POST" });
        state.rows.push(copy);
      }
      render();
      return;
    }
    const delRow = e.target.closest("[data-del-row]");
    if (delRow) { await deleteRows([Number(delRow.dataset.delRow)]); drawer.hidden = true; return; }
    const dupRow = e.target.closest("[data-dup-row]");
    if (dupRow) {
      const copy = await api(`${state.routes.row}/${dupRow.dataset.dupRow}/duplicate`, { method: "POST" });
      state.rows.push(copy);
      render();
      return;
    }
    const rename = e.target.closest("[data-rename]");
    if (rename) {
      const name = prompt("Name", rename.dataset.name);
      if (!name) return;
      const kind = rename.dataset.rename;
      const url = kind === "database" ? `${state.routes.database}/${rename.dataset.id}`
        : kind === "table" ? `${state.routes.table}/${rename.dataset.id}`
        : `${state.routes.view}/${rename.dataset.id}`;
      await api(url, { method: "PATCH", body: JSON.stringify({ name }) });
      location.reload();
      return;
    }
    const del = e.target.closest("[data-delete]");
    if (del) {
      if (!confirm("Delete this permanently?")) return;
      const kind = del.dataset.delete;
      const url = kind === "database" ? `${state.routes.database}/${del.dataset.id}`
        : kind === "table" ? `${state.routes.table}/${del.dataset.id}`
        : `${state.routes.view}/${del.dataset.id}`;
      const res = await api(url, { method: "DELETE" });
      location.href = res.redirect || state.urls.table;
      return;
    }
    const createTable = e.target.closest("[data-create-table]");
    if (createTable) {
      const name = prompt("Table name", "Table");
      if (!name) return;
      const form = document.createElement("form");
      form.method = "post";
      form.action = `/database/${createTable.dataset.createTable}/tables`;
      form.innerHTML = `<input name="_token" value="${state.csrf}"><input name="name" value="${esc(name)}">`;
      document.body.appendChild(form);
      form.submit();
    }
  });

  document.addEventListener("change", async (e) => {
    const sel = e.target.closest("[data-select-row]");
    if (sel) {
      const id = Number(sel.dataset.selectRow);
      if (sel.checked) selected.add(id); else selected.delete(id);
      render();
      return;
    }
    const hide = e.target.closest("[data-hide]");
    if (hide) {
      const id = Number(hide.dataset.hide);
      const hidden = new Set((state.view.hidden_fields || []).map(Number));
      if (hide.checked) hidden.delete(id); else hidden.add(id);
      state.view.hidden_fields = [...hidden];
      persistFiltersSorts();
      return;
    }
    const filter = e.target.closest("[data-filter]");
    if (filter) {
      const i = Number(filter.dataset.filter);
      const key = filter.dataset.key;
      state.view.filters[i][key] = key === "field_id" ? Number(filter.value) : filter.value;
      if (key !== "value") persistFiltersSorts();
      return;
    }
    const sort = e.target.closest("[data-sort]");
    if (sort) {
      const i = Number(sort.dataset.sort);
      state.view.sorts[i][sort.dataset.key] = sort.dataset.key === "field_id" ? Number(sort.value) : sort.value;
      persistFiltersSorts();
      return;
    }
    const group = e.target.closest("[data-group]");
    if (group) {
      state.view.groups[Number(group.dataset.group)].field_id = Number(group.value);
      persistFiltersSorts();
      return;
    }
    const drawerField = e.target.closest("[data-drawer-field]");
    if (drawerField) {
      let value = drawerField.type === "checkbox" ? drawerField.checked : drawerField.value;
      if (drawerField.type === "number") value = value === "" ? null : Number(value);
      await updateRow(Number(drawerField.dataset.row), { [drawerField.dataset.drawerField]: value });
      openDrawer(Number(drawerField.dataset.row));
      return;
    }
    if (e.target.matches("[data-multi]")) {
      const fieldId = e.target.dataset.multi;
      const rowId = Number(e.target.dataset.row);
      const boxes = [...document.querySelectorAll(`[data-multi="${fieldId}"][data-row="${rowId}"]`)];
      await updateRow(rowId, { [fieldId]: boxes.filter((b) => b.checked).map((b) => b.value) });
      openDrawer(rowId);
    }
  });

  document.addEventListener("keydown", async (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === "Enter") {
      const add = document.querySelector("[data-add-row]");
      if (add && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) addRow();
    }
  });

  const importInput = document.getElementById("import-csv");
  if (importInput) {
    importInput.addEventListener("change", async () => {
      if (!importInput.files[0]) return;
      const fd = new FormData();
      fd.append("file", importInput.files[0]);
      fd.append("_token", state.csrf);
      await fetch(state.urls.import, { method: "POST", body: fd, headers: { Accept: "application/json", "X-CSRF-TOKEN": state.csrf } });
      location.reload();
    });
  }

  document.querySelectorAll("[data-filter][data-key='value']").forEach(() => {});
  document.getElementById("panel-filters")?.addEventListener("change", (e) => {
    if (e.target.matches("[data-filter][data-key='value']")) {
      /* committed on Enter via blur-less save */
    }
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && e.target.matches("[data-filter][data-key='value']")) {
      persistFiltersSorts();
    }
  });

  render();
})();
