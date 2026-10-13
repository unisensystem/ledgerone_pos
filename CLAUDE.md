# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LedgerOne ERP (formerly "FuelingSys ERP" — the old name still appears in some comments/docs) is a multi-tenant SaaS ERP system: sales, purchasing, inventory, HRM, banking, chart of accounts, financial reports, manufacturing, rent, and vouchers. It's a classic PHP + vanilla JS/HTML application served by XAMPP/Apache — **no framework, no bundler, no package manager for the frontend, no test suite**. `composer.json` only pulls in `dompdf` (PDF generation) and `picqer/php-barcode-generator`; `package.json`'s `openai` dependency is not referenced anywhere in the code.

## Commands

- Install PHP deps: `composer install`
- There is no build step, linter, or test runner configured. Verify changes by running the app under XAMPP/Apache and exercising the page in a browser (Network tab is the main debugging tool for API calls).
- Deployment is automatic: `.github/workflows/deploy.yml` runs on every push to `main` — it SSHes into the Oracle VM, does `git fetch && git reset --hard origin/main && git clean -fd`, then restarts php-fpm. **There is no CI test gate**, so pushes to `main` go straight to the sandbox server.

## Architecture

### Client/server mirroring
The frontend and backend are organized as parallel module trees, and this mirroring is the main navigation aid in this codebase:

```
client/pages/<module>/<feature>/<feature>.php   # page shell (HTML + session/auth guard)
client/assets/js/<module>/<feature>/<feature>.js # vanilla JS, fetch()-based
server/api/<module>/<feature>/<feature>.php      # JSON API endpoint(s)
```
Modules include `purchase`, `sale`, `inventory`, `hrm`, `banking`, `chart_of_accounts`, `customer_supplier`, `financial_reports`, `manufacturing`, `rent`, `master_setup`, `system_setup`, `project_report`, `vouchers`. When changing a feature, expect to touch the `.php` page, its `.js` file, and one or more sibling `server/api/.../*.php` endpoints (list/add/edit/delete/get-* are usually split into separate files, e.g. `get-suppliers.php`, `purchase-add.php`, `purchase-list.php`).

### Shared includes (`includes/`)
- `connection.php` — creates the PDO `$pdo` connection to the tenant DB. It is `.gitignore`d (environment-specific) but currently tracked with hardcoded local dev credentials; treat DB credentials here as environment config, not something to commit changes to carelessly.
- `dashboard.php` — single include that injects the navbar container and loads `dashboard-core.js`; included at the top of nearly every page in `client/pages/`.
- `check_permission.php` / `permissions.php` — RBAC helpers (`hasPermission()`, `getUserPermissions()`) that check a `role_permissions` table keyed by `role_id` + `category` + `form_name` + `sub_permission`. Two versions exist with slightly different signatures (one keyed by `user_id`+category+form+sub_permission with an in-request static cache, one keyed by `role_id`+category+form_name+`tenant_id`) — check which one a given file actually includes before assuming the signature.
- `encryption.php` — shared encryption helpers.

### Multi-tenancy model
There are two databases:
- `ledgerone_public` — cross-tenant data: tenants, subscriptions, billing logs, plans. Used by `server/api/auth/*` (login/register/checkout/trial activation).
- `ledgerone_tenant` — shared schema for all tenants' business data (invoices, customers, products, etc.), scoped by a `tenant_id` column on the relevant tables. Most `server/api/**` endpoints connect here via `includes/connection.php`.

Auth is PHP session-based: `$_SESSION['user_id']` and `$_SESSION['tenant_id']` are set at login and checked at the top of both page shells (redirect to login on missing session) and API endpoints (401 JSON response on missing session). When writing a new API endpoint, follow the existing pattern: require `connection.php`, set JSON + CORS headers, verify `$_SERVER['REQUEST_METHOD']`, pull `user_id`/`tenant_id` from the session, and scope all queries by `tenant_id` (and usually `company_id`/`branch_id` too — most business tables are further scoped per-company/branch).

### Database migrations
There is no migration framework/ORM. Schema changes are plain `.sql` files (and a couple of one-off `.php` scripts) dropped into `database/migrations/` and `migrations/`, named descriptively (e.g. `add_tenant_id_to_tax_rates.sql`, `create_purchase_invoice_taxes_table.sql`). `database/ledgerone_public.sql` and `database/ledgerone_tenant.sql` are full schema dumps. When adding a schema change, add a new descriptively-named `.sql` file rather than editing a dump in place, and run it manually against the target DB (there's no automatic migration runner beyond the ad hoc `run_migration.php` / `server/run_migration.php` scripts).

### Tax calculation subsystem
Sales/purchase taxation (item-level vs invoice-level tax, formula-driven tax rates, withholding/AIT tax) is one of the more intricate subsystems in the app. Before touching anything under `client/assets/js/sale/pos_invoice/*tax*`, `pos-add.js`, or `server/api/sale/pos_invoice/calculate-tax.php` / `get-invoice-level-taxes.php`, read `documentation/TAXATION_ARCHITECTURE_GUIDE.md` — it documents the data flow (`row.dataset.*` metadata, `evaluateFormula()`, `collectInvoiceTaxes()`, the MutationObserver-driven auto-loading in `tax-integration.js`) in detail. The same item-level/invoice-level tax model is reused by purchase invoices and sale/purchase returns.

### Design system
`documentation/ledgerone_erp_ds_light.md` and `ledgerone_erp_ds_dark.md` define the light/dark color tokens and component styling conventions (buttons, forms, cards). Match these when adding or styling UI rather than introducing new colors/spacing ad hoc.

### Repo hygiene note
The repo root currently has several one-off debugging/migration scripts (`fix_*.py`, `check_columns.php`, `check_supplier_index.php`, `temp_lines.txt`, `test-*.sql`, `run_migration.php`). These are scratch artifacts from prior debugging sessions, not part of the application architecture — don't treat them as canonical patterns to extend.
