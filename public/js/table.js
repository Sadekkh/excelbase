window.BaserowTable = (() => {
  let state = null;
  const api = window.Baserow.api;
  const toast = window.Baserow.toast;
  let stage = document.getElementById("view-stage");
  let drawer = document.getElementById("row-drawer");
  let selected = new Set();
  let calendarCursor = new Date();
  let active = null;
  let readOnly = false;
  let canBuild = false;

  function applyState(next) {
    state = next;
    stage = document.getElementById("view-stage");
    drawer = document.getElementById("row-drawer");
    selected = new Set();
    calendarCursor = new Date();
    active = null;
    readOnly = !!state.readOnly;
    canBuild = !!state.canBuild;
  }

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
    link: "M10 14a4 4 0 0 1 0-5.6l2-2a4 4 0 0 1 5.6 5.6L16 13M14 10a4 4 0 0 1 0 5.6l-2 2a4 4 0 1 1-5.6-5.6L8 10",
    file: "M7 3.5h7l5 5V20H7zM14 3.5V9h5",
    formula: "M6 5h12M8 5v14M6 19h5M14 12h4M16 10v4",
    ai: "M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2z",
    search: "M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13zM16 16.5 20 20.5",
    graph: "M5 19V9M11 19V5M17 19v-7M3 19h18",
    survey: "M5 4h14v16H5zM8 8h8M8 12h8M8 16h5",
    timeline: "M4 12h16M7 8v8M17 8v8M12 6v12",
    comment: "M5 5h14v10H9l-4 4z",
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
    if (field.type === "link_row") {
      return linkedLabels(field, value).join(", ");
    }
    if (field.type === "file") {
      return (value || []).map((f) => f.name).filter(Boolean).join(", ");
    }
    return value == null ? "" : String(value);
  }

  function pill(opt) {
    if (!opt) return "";
    const c = colorOf(opt.color);
    return `<span class="pill" style="background:${c.bg};color:${c.text}">${esc(opt.value)}</span>`;
  }

  function linkedLabels(field, value) {
    const tableId = field.options?.linked_table_id;
    const catalog = (state.linkedRows && state.linkedRows[tableId]) || [];
    return (value || []).map((id) => catalog.find((r) => Number(r.id) === Number(id))?.label || `#${id}`);
  }

  function stars(n, max = 5, rowId, fieldId) {
    const v = Number(n) || 0;
    const bits = [];
    for (let i = 1; i <= max; i++) {
      bits.push(`<button type="button" class="star-btn ${i <= v ? "is-on" : ""}" data-rate="${rowId}:${fieldId}:${i}">★</button>`);
    }
    return `<span class="stars">${bits.join("")}</span>`;
  }

  function cellHTML(field, value, rowId) {
    if (field.type === "boolean") {
      return `<span class="bool ${value ? "is-on" : ""}">${value ? icon("check", 12) : ""}</span>`;
    }
    if (field.type === "single_select") return pill(optionLabel(field, value));
    if (field.type === "multiple_select") {
      return (value || []).map((id) => pill(optionLabel(field, id))).join(" ");
    }
    if (field.type === "rating") return stars(value, field.options?.max || 5, rowId, field.id);
    if (field.type === "link_row") {
      return linkedLabels(field, value).map((label) => `<span class="pill" style="background:#dae4fd;color:#083663">${esc(label)}</span>`).join(" ");
    }
    if (field.type === "file") {
      return (value || []).map((f) => `<a class="file-chip" href="${esc(f.url)}" target="_blank" rel="noreferrer">${esc(f.name || "File")}</a>`).join(" ");
    }
    if (field.type === "url" && value) return `<a href="${esc(value)}" target="_blank" rel="noreferrer">${esc(value)}</a>`;
    if (field.type === "email" && value) return `<a href="mailto:${esc(value)}">${esc(value)}</a>`;
    return esc(value);
  }

  function empty(field, value) {
    if (value == null || value === "") return true;
    if (["multiple_select", "link_row", "file"].includes(field.type) && Array.isArray(value) && !value.length) return true;
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

  function rowColorStyle(row) {
    const fieldId = state.view.field_options?.row_color_field_id;
    if (!fieldId) return "";
    const field = fieldById(fieldId);
    const opt = field && optionLabel(field, row.values[fieldId]);
    if (!opt) return "";
    const c = colorOf(opt.color);
    return `style="box-shadow:inset 3px 0 0 ${c.text}"`;
  }

  function render() {
    if (!state || !stage) return;
    selected = new Set([...selected].filter((id) => state.rows.some((r) => r.id === id)));
    if (state.view.type === "gallery") return renderGallery();
    if (state.view.type === "kanban") return renderKanban();
    if (state.view.type === "calendar") return renderCalendar();
    if (state.view.type === "timeline") return renderTimeline();
    if (state.view.type === "graph") return renderGraph();
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
                  <th class="${f.primary ? "grid__primary" : ""}" style="width:${f.width}px;min-width:${f.width}px" data-field-id="${f.id}">
                    <div class="field-head" data-field-menu="${f.id}">
                      <span class="field-head__icon">${icon(f.icon)}</span>
                      <span class="field-head__name">${esc(f.name)}</span>
                    </div>
                    <div class="col-resizer" data-resize="${f.id}"></div>
                  </th>`).join("")}
                <th style="min-width:140px">${canBuild ? `<button class="add-field" data-add-field>${icon("plus")} Add field</button>` : ""}</th>
              </tr>
            </thead>
            <tbody>
              ${groupedRows(rows).map((entry, i) => entry.kind === "group" ? `
                <tr class="grid__row"><td class="grid__gutter"></td><td colspan="${fields.length + 1}">
                  <div class="cell" style="font-weight:600;background:var(--neutral-25)">${esc(entry.label)} · ${entry.count}</div>
                </td></tr>` : `
                <tr class="grid__row ${selected.has(entry.row.id) ? "is-selected" : ""}" data-row-id="${entry.row.id}" ${rowColorStyle(entry.row)}>
                  <td class="grid__gutter">
                    <div class="grid__gutter-inner">
                      <input type="checkbox" class="grid__check" data-select-row="${entry.row.id}" ${selected.has(entry.row.id) ? "checked" : ""}>
                      <span>${entry.index}</span>
                      ${readOnly ? "" : `<button type="button" data-open-row="${entry.row.id}" title="Expand">${icon("expand", 13)}</button>`}
                    </div>
                  </td>
                  ${fields.map((f) => `
                    <td class="${f.primary ? "grid__primary" : ""}" style="width:${f.width}px;min-width:${f.width}px">
                      <div class="cell ${f.primary ? "is-primary" : ""} ${empty(f, entry.row.values[f.id]) ? "cell--empty" : ""} ${active && active.rowId === entry.row.id && Number(active.fieldId) === Number(f.id) ? "is-active" : ""}" data-edit="${entry.row.id}:${f.id}">
                        ${cellHTML(f, entry.row.values[f.id], entry.row.id)}
                      </div>
                    </td>`).join("")}
                  <td></td>
                </tr>`).join("")}
              <tr>
                <td class="grid__gutter"></td>
                <td colspan="${fields.length + 1}">${readOnly ? "" : `<button class="add-row" data-add-row>${icon("plus")} New row</button>`}</td>
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
            <div><dt>${esc(f.name)}</dt><dd>${cellHTML(f, row.values[f.id], row.id) || "—"}</dd></div>`).join("")}</dl>
        </article>`).join("")}
      ${readOnly ? "" : `<button class="card gallery__add" data-add-row>${icon("plus")} New row</button>`}
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

  function renderTimeline() {
    const dates = state.fields.filter((f) => f.type === "date");
    const start = fieldById(state.view.field_options?.start_field_id) || dates[0];
    const end = fieldById(state.view.field_options?.end_field_id) || dates[1] || dates[0];
    const primary = state.fields.find((f) => f.primary);
    if (!start) {
      stage.innerHTML = `<div class="empty-state" style="margin:24px"><h2>Timeline needs a date field</h2><p>Add a start date (and optionally an end date) to plot bars.</p></div>`;
      return;
    }
    const parsed = state.rows.map((row) => {
      const s = Date.parse(row.values[start.id] || "") || null;
      const e = Date.parse((end && row.values[end.id]) || row.values[start.id] || "") || s;
      return { row, s, e };
    }).filter((x) => x.s);
    const min = parsed.length ? Math.min(...parsed.map((x) => x.s)) : Date.now();
    const max = parsed.length ? Math.max(...parsed.map((x) => x.e || x.s)) : min + 86400000 * 14;
    const span = Math.max(max - min, 86400000);
    stage.innerHTML = `<div class="timeline">
      <div class="timeline__tools">
        <label>Start <select id="tl-start">${dates.map((f) => `<option value="${f.id}" ${f.id === start.id ? "selected" : ""}>${esc(f.name)}</option>`).join("")}</select></label>
        <label>End <select id="tl-end">${dates.map((f) => `<option value="${f.id}" ${end && f.id === end.id ? "selected" : ""}>${esc(f.name)}</option>`).join("")}</select></label>
      </div>
      ${parsed.map(({ row, s, e }) => {
        const left = ((s - min) / span) * 100;
        const width = Math.max(3, (((e || s) - s) / span) * 100);
        return `<div class="timeline__row" data-open-row="${row.id}">
          <strong>${esc(display(primary, row.values[primary.id]) || "Untitled")}</strong>
          <div class="timeline__track"><div class="timeline__bar" style="left:${left}%;width:${width}%"></div></div>
        </div>`;
      }).join("") || `<div class="empty-state"><p>No dated rows yet.</p></div>`}
    </div>`;
    stage.querySelector("#tl-start").onchange = async (ev) => {
      state.view.field_options = { ...(state.view.field_options || {}), start_field_id: Number(ev.target.value) };
      await saveView({ field_options: state.view.field_options });
      renderTimeline();
    };
    stage.querySelector("#tl-end").onchange = async (ev) => {
      state.view.field_options = { ...(state.view.field_options || {}), end_field_id: Number(ev.target.value) };
      await saveView({ field_options: state.view.field_options });
      renderTimeline();
    };
  }

  function renderGraph() {
    const cats = state.fields.filter((f) => f.type === "single_select");
    const nums = state.fields.filter((f) => f.type === "number" || f.type === "rating");
    const cat = fieldById(state.view.field_options?.graph_field_id) || cats[0];
    const metric = fieldById(state.view.field_options?.graph_metric_id);
    if (!cat) {
      stage.innerHTML = `<div class="empty-state" style="margin:24px"><h2>Graph needs a single select field</h2><p>Add a single select to chart counts or totals.</p></div>`;
      return;
    }
    const options = cat.options?.options || [];
    const buckets = options.map((opt) => {
      const rows = state.rows.filter((r) => String(r.values[cat.id] || "") === String(opt.id));
      const value = metric
        ? rows.reduce((sum, row) => sum + (Number(row.values[metric.id]) || 0), 0)
        : rows.length;
      return { opt, value, count: rows.length };
    });
    const max = Math.max(1, ...buckets.map((b) => b.value));
    stage.innerHTML = `<div class="graph">
      <div class="graph__tools">
        <label>Group by <select id="graph-cat">${cats.map((f) => `<option value="${f.id}" ${f.id === cat.id ? "selected" : ""}>${esc(f.name)}</option>`).join("")}</select></label>
        <label>Value <select id="graph-metric">
          <option value="">Count of rows</option>
          ${nums.map((f) => `<option value="${f.id}" ${metric && f.id === metric.id ? "selected" : ""}>Sum of ${esc(f.name)}</option>`).join("")}
        </select></label>
      </div>
      <div class="graph__bars">
        ${buckets.map((b) => {
          const c = colorOf(b.opt.color);
          const h = Math.round((b.value / max) * 180);
          return `<div class="graph__col">
            <div class="graph__value">${esc(String(b.value))}</div>
            <div class="graph__bar" style="height:${h}px;background:${c.text}"></div>
            <div class="graph__label">${esc(b.opt.value)}</div>
          </div>`;
        }).join("")}
      </div>
    </div>`;
    stage.querySelector("#graph-cat").onchange = async (ev) => {
      state.view.field_options = { ...(state.view.field_options || {}), graph_field_id: Number(ev.target.value) };
      await saveView({ field_options: state.view.field_options });
      renderGraph();
    };
    stage.querySelector("#graph-metric").onchange = async (ev) => {
      state.view.field_options = { ...(state.view.field_options || {}), graph_metric_id: ev.target.value ? Number(ev.target.value) : null };
      await saveView({ field_options: state.view.field_options });
      renderGraph();
    };
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
            ${cfg.hide_branding ? "" : `<div class="public-form__brand">${icon("text", 18)} Baserow</div>`}
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
        <label class="check"><input type="checkbox" id="form-survey" ${cfg.mode === "survey" ? "checked" : ""}> Survey mode (one question at a time)</label>
        <label class="check"><input type="checkbox" id="form-branding" ${cfg.hide_branding ? "checked" : ""}> Hide Baserow branding</label>
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
    if (!row) return;
    Object.assign(row.values, values);
    render();
    try {
      const updated = await api(`${state.routes.row}/${id}`, { method: "PATCH", body: JSON.stringify({ values }) });
      Object.assign(row, updated);
      render();
    } catch (err) {
      toast(err.message || "Could not save row");
    }
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
    const q = document.getElementById("grid-search")?.value || "";
    if (window.BaserowShell?.reloadSheet) {
      window.BaserowShell.reloadSheet({ viewId: state.view.id, search: q });
      return;
    }
    const url = new URL(state.urls.sheet || state.urls.table, location.origin);
    url.searchParams.set("view", state.view.id);
    if (q) url.searchParams.set("search", q);
    if (state.urls.sheet) {
      api(url.toString()).then((data) => {
        applyState(data.bootstrap);
        render();
      }).catch((err) => toast(err.message || "Could not reload sheet"));
      return;
    }
    location.href = url.toString();
  }

  function startEdit(rowId, fieldId, cell) {
    const field = fieldById(fieldId);
    const row = state.rows.find((r) => r.id === rowId);
    active = { rowId, fieldId: Number(fieldId) };
    if (readOnly || !field || !row || field.read_only) return;
    if (field.type === "boolean") {
      updateRow(rowId, { [field.id]: !row.values[field.id] });
      return;
    }
    if (field.type === "rating") return;
    if (field.type === "link_row") { openLinkPicker(rowId, field, cell); return; }
    if (field.type === "file") { pickFile(rowId, field); return; }
    if (field.type === "multiple_select") { openMultiPicker(rowId, field, cell); return; }
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

  function openMultiPicker(rowId, field, cell) {
    const current = new Set((state.rows.find((r) => r.id === rowId).values[field.id] || []).map(String));
    cell.classList.add("is-editing");
    cell.innerHTML = `<div class="cell-pop">${(field.options?.options || []).map((o) =>
      `<label class="check"><input type="checkbox" value="${esc(o.id)}" ${current.has(String(o.id)) ? "checked" : ""}> ${esc(o.value)}</label>`
    ).join("")}<button type="button" class="btn btn--primary" data-apply-multi>Done</button></div>`;
    cell.querySelector("[data-apply-multi]").onclick = async () => {
      const ids = [...cell.querySelectorAll("input:checked")].map((i) => i.value);
      await updateRow(rowId, { [field.id]: ids });
    };
  }

  function openLinkPicker(rowId, field, cell) {
    const tableId = field.options?.linked_table_id;
    const catalog = (state.linkedRows && state.linkedRows[tableId]) || [];
    const current = new Set((state.rows.find((r) => r.id === rowId).values[field.id] || []).map(String));
    cell.classList.add("is-editing");
    cell.innerHTML = `<div class="cell-pop">${catalog.map((r) =>
      `<label class="check"><input type="checkbox" value="${r.id}" ${current.has(String(r.id)) ? "checked" : ""}> ${esc(r.label)}</label>`
    ).join("") || "<p>No rows in the linked table.</p>"}<button type="button" class="btn btn--primary" data-apply-link>Done</button></div>`;
    cell.querySelector("[data-apply-link]").onclick = async () => {
      const ids = [...cell.querySelectorAll("input:checked")].map((i) => Number(i.value));
      await updateRow(rowId, { [field.id]: ids });
    };
  }

  async function pickFile(rowId, field) {
    const input = document.createElement("input");
    input.type = "file";
    input.onchange = async () => {
      if (!input.files[0]) return;
      const fd = new FormData();
      fd.append("file", input.files[0]);
      const uploaded = await api(state.urls.upload, { method: "POST", body: fd });
      const row = state.rows.find((r) => r.id === rowId);
      const next = [...(row.values[field.id] || []), uploaded];
      await updateRow(rowId, { [field.id]: next });
    };
    input.click();
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
        ${readOnly ? "" : `<div class="comments">
          <h3>${icon("comment", 14)} Comments</h3>
          <div id="comment-list"><p class="hint">Loading comments…</p></div>
          <form class="comment-form" id="comment-form" data-row="${row.id}">
            <textarea name="body" rows="2" required placeholder="Write a comment"></textarea>
            <button type="submit" class="btn btn--primary">Comment</button>
          </form>
        </div>`}
      </div>`;
    if (!readOnly) loadComments(rowId);
  }

  async function loadComments(rowId) {
    const box = document.getElementById("comment-list");
    if (!box) return;
    try {
      const data = await api(`${state.routes.row}/${rowId}/comments`);
      const items = data.comments || [];
      box.innerHTML = items.length
        ? items.map((c) => `<article class="comment"><strong>${esc(c.author)}</strong><time>${esc((c.created_at || "").slice(0, 16).replace("T", " "))}</time><p>${esc(c.body)}</p></article>`).join("")
        : `<p class="hint">No comments yet. Start the thread.</p>`;
    } catch (_) {
      box.innerHTML = `<p class="hint">Could not load comments.</p>`;
    }
    const form = document.getElementById("comment-form");
    if (form) {
      form.onsubmit = async (e) => {
        e.preventDefault();
        const body = form.querySelector("textarea").value.trim();
        if (!body) return;
        await api(`${state.routes.row}/${rowId}/comments`, { method: "POST", body: JSON.stringify({ body }) });
        form.reset();
        loadComments(rowId);
        toast("Comment added");
      };
    }
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
      } else if (type === "link_row") {
        const tables = state.siblingTables || [];
        const currentId = current?.options?.linked_table_id || "";
        optionsBox.innerHTML = `<label class="field"><span>Link to table</span>
          <select name="linked_table_id">${tables.filter((t) => t.id !== state.table.id).map((t) =>
            `<option value="${t.id}" ${Number(t.id) === Number(currentId) ? "selected" : ""}>${esc(t.name)}</option>`).join("")}</select></label>`;
      } else if (type === "lookup" || type === "count") {
        const links = (state.fields || []).filter((f) => f.type === "link_row");
        if (!links.length) {
          optionsBox.innerHTML = `<p class="hint">Add a Link to table field first, then you can look up or count related rows.</p>`;
          return;
        }
        const selectedLink = Number(current?.options?.link_field_id || links[0].id);
        const linkField = links.find((f) => Number(f.id) === selectedLink) || links[0];
        const remote = (state.linkedFields && state.linkedFields[linkField.options?.linked_table_id]) || [];
        optionsBox.innerHTML = `<label class="field"><span>Link field</span>
          <select name="link_field_id" id="lookup-link">${links.map((f) => `<option value="${f.id}" ${Number(f.id) === selectedLink ? "selected" : ""}>${esc(f.name)}</option>`).join("")}</select></label>
          ${type === "lookup" ? `<label class="field"><span>Field on the linked table</span>
            <select name="lookup_field_id">${remote.map((f) => `<option value="${f.id}" ${Number(current?.options?.lookup_field_id) === f.id ? "selected" : ""}>${esc(f.name)}</option>`).join("")}</select></label>` : ""}`;
        document.getElementById("lookup-link")?.addEventListener("change", (ev) => {
          paintOptions(type, { ...(current || {}), options: { ...(current?.options || {}), link_field_id: Number(ev.target.value) } });
        });
      } else if (type === "formula") {
        optionsBox.innerHTML = `<label class="field"><span>Formula</span>
          <input name="formula" id="formula-input" value="${esc(current?.options?.formula || "")}" placeholder="UPPER({Name}) or {Amount} * 0.1"></label>
          <label class="field"><span>Generate with AI</span>
            <input id="formula-prompt" placeholder="Commission is 10% of Amount"></label>
          <button type="button" class="btn btn--ghost" id="formula-ai">Generate formula</button>
          <p class="hint">Use {Field name}. Functions: UPPER, LOWER, LEN, CONCAT, IF. Math: + − * /</p>`;
        document.getElementById("formula-ai")?.addEventListener("click", () => {
          const prompt = document.getElementById("formula-prompt").value;
          document.getElementById("formula-input").value = generateFormula(prompt);
        });
      } else if (type === "ai") {
        const sources = (state.fields || []).filter((f) => !f.read_only);
        const currentMode = current?.options?.mode || current?.options?.ai_mode || "summarize";
        optionsBox.innerHTML = `<label class="field"><span>Source field</span>
          <select name="source_field_id">
            <option value="">Entire row</option>
            ${sources.map((f) => `<option value="${f.id}" ${Number(current?.options?.source_field_id) === f.id ? "selected" : ""}>${esc(f.name)}</option>`).join("")}
          </select></label>
          <label class="field"><span>AI mode</span>
          <select name="ai_mode">
            <option value="summarize" ${currentMode === "summarize" ? "selected" : ""}>Summarize</option>
            <option value="classify" ${currentMode === "classify" ? "selected" : ""}>Classify (positive / negative / neutral)</option>
            <option value="extract_email" ${currentMode === "extract_email" ? "selected" : ""}>Extract email</option>
          </select></label>
          <p class="hint">Runs locally — no API key. Premium-style AI field without sending data out.</p>`;
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
      } else if (type === "link_row") {
        payload.options = { linked_table_id: Number(fd.get("linked_table_id")) };
      } else if (type === "formula") {
        payload.options = { formula: fd.get("formula") || "" };
      } else if (type === "ai") {
        payload.options = { source_field_id: fd.get("source_field_id") ? Number(fd.get("source_field_id")) : null, mode: fd.get("ai_mode") || "summarize" };
      } else if (type === "lookup") {
        payload.options = { link_field_id: Number(fd.get("link_field_id")), lookup_field_id: Number(fd.get("lookup_field_id")) };
      } else if (type === "count") {
        payload.options = { link_field_id: Number(fd.get("link_field_id")) };
      }
      try {
        if (existing) {
          const updated = await api(`${state.routes.field}/${existing.id}`, { method: "PATCH", body: JSON.stringify(payload) });
          Object.assign(existing, updated);
        } else {
          const created = await api(state.urls.fields, { method: "POST", body: JSON.stringify(payload) });
          state.fields.push(created);
        }
        root.innerHTML = "";
        if (["formula", "ai", "lookup", "count"].includes(type)) {
          reload();
          return;
        }
        render();
        toast(existing ? "Field updated" : "Field created");
      } catch (err) {
        toast(err.message || "Could not save field");
      }
    };
  }

  function generateFormula(prompt) {
    const list = (state.fields || []).filter((f) => !["formula", "ai"].includes(f.type));
    if (!list.length) return "";
    const needle = (prompt || "").toLowerCase();
    const named = list.filter((f) => needle.includes(f.name.toLowerCase()));
    const first = named[0] || list[0];
    const numbers = list.filter((f) => f.type === "number" || f.type === "rating");
    if (/upper|caps|uppercase/.test(needle)) return `UPPER({${first.name}})`;
    if (/lower|lowercase/.test(needle)) return `LOWER({${first.name}})`;
    if (/\blen\b|length|characters/.test(needle)) return `LEN({${first.name}})`;
    if (/concat|combine|join|merge/.test(needle) && (named.length >= 2 || list.length >= 2)) {
      const a = named[0] || list[0];
      const b = named[1] || list[1];
      return `CONCAT({${a.name}}, " ", {${b.name}})`;
    }
    if (/percent|commission|10%|0\.1/.test(needle) && numbers[0]) return `{${numbers[0].name}} * 0.1`;
    if (/\*|times|multipl|product/.test(needle) && numbers.length >= 2) return `{${numbers[0].name}} * {${numbers[1].name}}`;
    if (/\+|plus|sum|add/.test(needle) && numbers.length >= 2) return `{${numbers[0].name}} + {${numbers[1].name}}`;
    if (/if|when|greater|above/.test(needle) && numbers[0]) return `IF({${numbers[0].name}}>0, "Yes", "No")`;
    return `UPPER({${first.name}})`;
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
    if (!state) return;
    const t = e.target.closest("[data-add-field]");
    if (t) { if (canBuild) fieldModal(); return; }
    const fh = e.target.closest("[data-field-menu]");
    if (fh) { if (canBuild) fieldModal(fieldById(fh.dataset.fieldMenu)); return; }
    const addRowBtn = e.target.closest("[data-add-row]");
    if (addRowBtn) {
      let preset = {};
      try { preset = JSON.parse(addRowBtn.dataset.preset || "{}"); } catch (_) {}
      await addRow(preset);
      return;
    }
    const edit = e.target.closest("[data-edit]");
    if (edit && !edit.classList.contains("is-editing") && !e.target.closest("[data-rate]")) {
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
    if (createView && canBuild && !window.BaserowShell) {
      try {
        const created = await api(state.urls.views, {
          method: "POST",
          body: JSON.stringify({
            type: createView.dataset.createView,
            is_personal: !!document.getElementById("create-personal")?.checked,
          }),
        });
        state.view.id = created.id;
        reload();
      } catch (err) {
        toast(err.message || "Could not create view");
      }
      return;
    }
    const togglePersonal = e.target.closest("[data-toggle-personal]");
    if (togglePersonal) {
      await saveView({ is_personal: !state.view.is_personal });
      reload();
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
    const rate = e.target.closest("[data-rate]");
    if (rate && !readOnly) {
      e.preventDefault();
      e.stopPropagation();
      const [rowId, fieldId, n] = rate.dataset.rate.split(":");
      await updateRow(Number(rowId), { [fieldId]: Number(n) });
      return;
    }
    const rowColor = e.target.closest("[data-row-color]");
    if (rowColor) {
      state.view.field_options = state.view.field_options || {};
      state.view.field_options.row_color_field_id = rowColor.dataset.rowColor ? Number(rowColor.dataset.rowColor) : null;
      await saveView({ field_options: state.view.field_options });
      window.Baserow.closeMenus();
      render();
      return;
    }
    const share = e.target.closest("[data-share-view]");
    if (share) {
      const updated = await api(state.urls.view, { method: "PATCH", body: JSON.stringify({ public: true }) });
      Object.assign(state.view, updated);
      const url = state.view.type === "form"
        ? `${location.origin}/form/${state.view.public_slug}`
        : `${location.origin}/shared/${state.view.public_slug}`;
      try { await navigator.clipboard.writeText(url); toast("Public link copied"); } catch (_) { toast(url); }
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
          mode: document.getElementById("form-survey")?.checked ? "survey" : "form",
          hide_branding: !!document.getElementById("form-branding")?.checked,
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
      if (window.BaserowShell?.refreshBoot) await window.BaserowShell.refreshBoot();
      reload();
      return;
    }
    const del = e.target.closest("[data-delete]");
    if (del) {
      if (!confirm("Delete this permanently?")) return;
      const kind = del.dataset.delete;
      const url = kind === "database" ? `${state.routes.database}/${del.dataset.id}`
        : kind === "table" ? `${state.routes.table}/${del.dataset.id}`
        : `${state.routes.view}/${del.dataset.id}`;
      await api(url, { method: "DELETE" });
      if (window.BaserowShell?.refreshBoot) await window.BaserowShell.refreshBoot();
      if (kind === "table" && window.BaserowShell?.refreshBoot) {
        const next = (state.siblingTables || []).find((t) => t.id !== Number(del.dataset.id));
        if (next && window.BaserowShell.openSheet) {
          window.BaserowShell.openSheet(next.id);
          return;
        }
      }
      reload();
      return;
    }
    const createTable = e.target.closest("[data-create-table]");
    if (createTable) {
      const name = prompt("Table name", "Table");
      if (!name) return;
      try {
        const created = await api(`/database/${createTable.dataset.createTable}/tables`, {
          method: "POST",
          body: JSON.stringify({ name }),
        });
        if (window.BaserowShell?.refreshBoot) await window.BaserowShell.refreshBoot();
        if (window.BaserowShell?.openSheet) window.BaserowShell.openSheet(created.id);
      } catch (err) {
        toast(err.message || "Could not create table");
      }
    }
  });

  document.addEventListener("change", async (e) => {
    if (!state) return;
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
    if (!state) return;
    if ((e.metaKey || e.ctrlKey) && e.key === "Enter" && !readOnly) {
      const add = document.querySelector("[data-add-row]");
      if (add && !["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement.tagName)) addRow();
    }
    const typing = ["INPUT", "TEXTAREA", "SELECT"].includes(document.activeElement.tagName);
    if (!active || typing || state.view.type !== "grid") return;
    const fields = visibleFields();
    const rows = state.rows;
    const rIdx = rows.findIndex((r) => r.id === active.rowId);
    const fIdx = fields.findIndex((f) => Number(f.id) === Number(active.fieldId));
    if ((e.metaKey || e.ctrlKey) && e.key === "c") {
      e.preventDefault();
      const row = rows[rIdx];
      const field = fields[fIdx];
      if (row && field) navigator.clipboard.writeText(display(field, row.values[field.id]));
      return;
    }
    if ((e.metaKey || e.ctrlKey) && e.key === "v" && !readOnly) {
      e.preventDefault();
      const text = await navigator.clipboard.readText();
      const field = fields[fIdx];
      const row = rows[rIdx];
      if (field && row && !field.read_only && !["boolean", "file", "link_row", "multiple_select"].includes(field.type)) {
        await updateRow(row.id, { [field.id]: field.type === "number" || field.type === "rating" ? Number(text) : text });
      }
      return;
    }
    if (e.key === "Enter" && !readOnly) {
      const cell = document.querySelector(`[data-edit="${active.rowId}:${active.fieldId}"]`);
      if (cell) startEdit(active.rowId, active.fieldId, cell);
      return;
    }
    let nr = rIdx;
    let nf = fIdx;
    if (e.key === "ArrowDown") nr = Math.min(rows.length - 1, rIdx + 1);
    else if (e.key === "ArrowUp") nr = Math.max(0, rIdx - 1);
    else if (e.key === "ArrowRight" || e.key === "Tab") { e.preventDefault(); nf = Math.min(fields.length - 1, fIdx + 1); }
    else if (e.key === "ArrowLeft") nf = Math.max(0, fIdx - 1);
    else return;
    if (rows[nr] && fields[nf]) {
      active = { rowId: rows[nr].id, fieldId: fields[nf].id };
      document.querySelectorAll(".cell.is-active").forEach((el) => el.classList.remove("is-active"));
      document.querySelector(`[data-edit="${active.rowId}:${active.fieldId}"]`)?.classList.add("is-active");
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
      reload();
    });
  }

  document.querySelectorAll("[data-filter][data-key='value']").forEach(() => {});
  document.getElementById("panel-filters")?.addEventListener("change", (e) => {
    if (e.target.matches("[data-filter][data-key='value']")) {
      /* committed on Enter via blur-less save */
    }
  });
  document.addEventListener("keydown", (e) => {
    if (!state) return;
    if (e.key === "Enter" && e.target.matches("[data-filter][data-key='value']")) {
      persistFiltersSorts();
    }
  });

  function mount(next) {
    applyState(next);
    render();
  }

  function unmount() {
    state = null;
    if (stage) stage.innerHTML = "";
    if (drawer) {
      drawer.hidden = true;
      drawer.innerHTML = "";
    }
  }

  const bootEl = document.getElementById("table-bootstrap");
  if (bootEl) {
    mount(JSON.parse(bootEl.textContent));
  }

  return { mount, unmount };
})();
