# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LedgerOne ERP is a multi-tenant ERP system for Distributors, Retailers, Wholesalers, Manufacturers, and SMEs. Core modules: Accounting, Inventory, HRM, Production, and Reporting.

## Development Environment

This project runs on XAMPP (Apache + MySQL). No build step is required — PHP files are served directly.

- **Start the app:** Open XAMPP Control Panel → Start Apache and MySQL, then visit `http://localhost/ledgerone_erp/`
- **Run a migration:** Execute the SQL files in `database/migrations/` against the tenant database via phpMyAdmin or CLI: `mysql -u root fuelingsys_tenant < database/migrations/<file>.sql`
- **Install PHP dependencies:** `composer install` (used for DOMPDF and barcode generation)
- **Install Node dependencies:** `npm install` (used for OpenAI integration)

There are no automated tests or a lint step.

## Architecture

### Multi-Tenant Design
Every database table includes `tenant_id` and `company_id` columns. All queries must filter by `$_SESSION['tenant_id']` to isolate tenant data. Never query without this filter.

### Request Flow
```
index.html → client/pages/auth/login.html → PHP session created → client/pages/<module>/*.php
                                                      ↓
                                          server/api/<module>/<action>.php (JSON responses)
```

Pages are PHP files that check session auth and render HTML. All data operations go through API endpoints that return JSON.

### Session & Auth
- `$_SESSION['user_id']`, `$_SESSION['tenant_id']`, `$_SESSION['role_id']`, `$_SESSION['company_id']` are the core session variables.
- Every API file must validate session at the top before any DB work:
  ```php
  session_start();
  $user_id = $_SESSION['user_id'] ?? null;
  $tenant_id = $_SESSION['tenant_id'] ?? null;
  if (!$user_id || !$tenant_id) { http_response_code(401); exit; }
  ```
- Every page must redirect unauthenticated users: `if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }`

### Permissions
`includes/permissions.php` exposes `hasPermission($pdo, $role_id, $category, $form_name, $tenant_id)`. Call it before any privileged operation. `includes/check_permission.php` provides cached helpers.

### Database Access
`includes/connection.php` (gitignored) provides a `$pdo` PDO instance. Always use prepared statements — never interpolate user input into SQL.

### API Pattern
All API files live in `server/api/<module>/`. They:
- Start with `require_once '../../../../includes/connection.php'`
- Set `Content-Type: application/json` and CORS headers
- Validate session, build parameterized queries, return `json_encode(['success' => true, 'data' => ...])`
- Use `$_SESSION['tenant_id']` as the first WHERE clause parameter

### Frontend Pattern
Pages in `client/pages/<module>/` are PHP files that include session auth, then render HTML with linked CSS from `client/assets/css/<module>/` and JS from `client/assets/js/<module>/`. JS files use `fetch()` or `XMLHttpRequest` to call API endpoints.

## Tech Stack

- **Backend:** Core PHP (no framework), PDO + MySQL
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **PDF generation:** DOMPDF (`vendor/`)
- **Barcodes:** `picqer/php-barcode-generator`
- **AI integration:** OpenAI SDK (Node.js, `node_modules/`)
- **Apache routing:** `.htaccess` with URL rewriting and custom error pages

## Code Style

- camelCase for variables and function names
- PascalCase for component names
- kebab-case for file names
- SQL: uppercase keywords, snake_case column/table names

## Project Structure

```
client/
  assets/css/<module>/       # Module-scoped stylesheets
  assets/js/<module>/        # Module-scoped JS
  assets/uploads/            # User-uploaded files
  pages/<module>/            # PHP page files
server/api/<module>/         # PHP JSON API endpoints
includes/                    # connection.php, dashboard.php, permissions.php (gitignored except permissions)
database/migrations/         # SQL migration files (run manually)
fpdf/                        # Legacy PDF library
vendor/                      # Composer packages
```

Key modules (appear in both `pages/` and `api/`): `sale`, `purchase`, `inventory`, `vouchers`, `financial_reports`, `hrm`, `manufacturing`, `banking`, `customer_supplier`, `system_setup`, `master_setup`.
