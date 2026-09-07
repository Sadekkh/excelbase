# Baserow

A Laravel + Blade SaaS recreation of [Baserow](https://baserow.io). No Vue, React, Alpine, or Livewire. Each **client is a workspace** on a **plan**, with **roles**, a **platform admin**, **dashboards**, and **automations**.

The app stays on `/app`. Each workspace is its own unit: tables, roles, permissions, Build mode, and look. You can install a French SME template (boulangerie, bâtiment, coffee shop, auto-entrepreneur), remove it, then keep adjusting the tables in Build. Invoices are a first-class French module: SIRET, sequential numbers, line **subtotal / tax / total**, TVA from workspace settings (or art. 293 B), an adjustable print template, and **Print / Save as PDF** in the browser (no popup).

A copy of the pre-ERP shell is tagged `snapshot-pre-erp`.

## Surfaces

- **Use** — Excel-like sheets, search, filters, and dashboards. Members and viewers only see this.
- **Build** — templates, Build with AI, invoices, databases, fields, automations, people, and plan.
- **Platform admin** (`/admin`) — all workspaces, users, and plan limits

## Roles

Owner, Admin, Builder, Member, Viewer. Members and viewers always land on the user surface.

## Plans

Free, Premium, and Advanced (local billing — choosing a plan changes limits immediately). Premium unlocks kanban/calendar/timeline/graph/survey, formula/AI/lookup fields, comments, extra exports, more automations and dashboards.

## Demo accounts

Password for all: `password`

| Email | Role |
| --- | --- |
| `demo@baserow.io` | Platform admin + owner of Acme Inc, Sales, and Fournil du Marais (boulangerie template) |
| `sam@baserow.io` | Builder on Acme Inc and on Sales |
| `maya@baserow.io` | Member on Acme Inc only — not on Sales until someone adds her from Acme |
| `noah@harborpine.com` | Owner of Harbor & Pine (Free client workspace) |

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

## Tests

```bash
php artisan test
```

## Stack

- Laravel 13, Blade, SQLite
- Session auth and workspace membership
- Vanilla JS and CSS — no frontend framework
