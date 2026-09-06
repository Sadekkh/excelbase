# Baserow

A Laravel + Blade recreation of [Baserow](https://baserow.io) — the open-source Airtable alternative. No Vue, React, Alpine, or Livewire. The UI is server-rendered Blade with vanilla JavaScript, styled to Baserow’s design tokens (Inter, `#5190ef`, 33px grid rows, 51px chrome).

This slice includes the **Premium** database features from [baserow.io/pricing](https://baserow.io/pricing): extra views, formula and AI fields, row comments, personal views, extra export formats, survey forms, and branding removal.

It does **not** clone the full Baserow cloud product (application builder, automations, dashboards, SSO, realtime collaboration, or hosted LLM providers).

## What you can do

- **Accounts** — sign in, sign up, demo workspace
- **Workspaces** — create, rename, delete; switch from the sidebar
- **Databases & tables** — create, rename, delete, duplicate tables
- **Fields** — text, long text, number, rating, boolean, date, single/multiple select, URL, email, phone, link to table, lookup, count, formula, AI, file, created on, last modified
- **Rows** — inline grid edit, expand drawer, bulk select, duplicate, delete, arrow-key navigation, copy/paste, row comments
- **Views** — grid, gallery, kanban, calendar, timeline, graph, form, survey
- **Premium view tools** — personal views, row coloring, extra-tall row height, hide Baserow branding on forms
- **Grid tools** — filter, sort, group, hide fields, row height, column resize, field summaries, CSV import
- **Export** — CSV, JSON, XML, Excel (tab-separated)
- **Sharing** — public grid/gallery/kanban/timeline/graph links and public forms that write into the table

The demo workspace (`Acme Inc`) ships with a CRM (Clients, Deals, Tasks) and a Product database. Deals include formula, AI, lookup, and count fields. Tasks include a timeline, a branded-off public form, a survey, and row comments. Clients include a personal “My leads” view and a graph.

## Run locally

Requires PHP 8.3+ and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=43127
```

Open [http://127.0.0.1:43127](http://127.0.0.1:43127).

**Demo account:** `demo@baserow.io` / `password`

Or click **Continue with the demo workspace** on the sign-in page.

## Tests

```bash
php artisan test
```

## Stack

- Laravel 13, Blade, SQLite
- Session auth
- Vanilla JS (`public/js`) and CSS (`public/css/baserow.css`) — no frontend framework and no Vite build step for the UI
