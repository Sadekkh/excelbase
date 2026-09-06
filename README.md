# Baserow

A Laravel + Blade recreation of [Baserow](https://baserow.io) — the open-source Airtable alternative. No Vue, React, Alpine, or Livewire. The UI is server-rendered Blade with vanilla JavaScript, styled to Baserow’s design tokens (Inter, `#5190ef`, 33px grid rows, 51px chrome).

## What you can do

- **Accounts** — sign in, sign up, demo workspace
- **Workspaces** — create, rename, delete; switch from the sidebar
- **Databases & tables** — create, rename, delete, duplicate tables
- **Fields** — text, long text, number, rating, boolean, date, single/multiple select, URL, email, phone, link to table, file, created on, last modified
- **Rows** — inline grid edit, expand drawer, bulk select, duplicate, delete, arrow-key navigation, copy/paste
- **Views** — grid, gallery, kanban, calendar, form
- **Grid tools** — filter, sort, group, hide fields, row height, column resize, field summaries, row coloring, CSV import/export
- **Sharing** — public grid/gallery/kanban links and public forms that write into the table

The demo workspace (`Acme Inc`) ships with a CRM (Clients, Deals, Tasks) and a Product database so the app looks like a real Baserow instance.

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
