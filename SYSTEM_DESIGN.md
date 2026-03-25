# EventSaaS System Design (Core PHP, No MVC)

## 1) Architecture

- Stack: Core PHP 7+, MySQL, mysqli, HTML/CSS/JS.
- Pattern: Module-first, include-driven, no MVC framework.
- Isolation: every tenant-owned business table carries `tenant_id` and must be queried with tenant scope.
- Shared hosting target: simple deployment, browser installer, lock file protection.

## 2) Folder Responsibilities

- `includes/`: config/bootstrap/helpers/guards/services (`auth.php`, `rbac.php`, `tenant.php`, `subscription.php`, `feature_limits.php`).
- `templates/`: layout building blocks (`header.php`, `topbar.php`, `sidebar.php`, `footer.php`).
- `modules/`: feature pages grouped by business domain.
- `actions/`: POST handlers, API-like endpoints, and command actions.
- `migrations/`: ordered SQL schema + seeds for setup.
- `uploads/`: tenant-owned uploaded assets (logos, profiles, items, catalogues).

## 3) Access & Roles

- Director (platform authority): approves tenant accounts, controls plans, locks/unlocks tenants, monitors platform analytics.
- Super Admin (tenant owner): manages tenant users, operations, branding, inventory, bookings, billing.
- Staff: access constrained via RBAC (`roles`, `permissions`, `role_permissions`, optional `user_permissions`).

## 4) Core Engines

- Authentication engine: director + tenant user login, session guards, status checks.
- Tenant context engine: resolves active tenant and enforces row-level scope in every query.
- RBAC engine: module/action based access checks.
- Audit engine: captures actor, action, module, before/after, IP, user agent, timestamp.
- Subscription/feature engine: plan checks for limits + booleans before create/export actions.

## 5) Data Model Highlights

- Operational layer: customers, items/services, bookings, returns, invoices, receipts, payments.
- Accountability layer: booking workers, worker accountability, event status history, audit logs.
- Community layer: marketplace profiles/catalogue, tenant messaging, broadcasts, ticketing.
- SaaS billing layer: plans, plan features, subscriptions, billing invoices/payments/reminders, account locks, feature overrides, usage stats.

## 6) Installer Design

- Entry point: `setup.php`.
- Runs preflight checks (PHP version, mysqli extension, folder permissions, migrations).
- Creates/selects DB, runs SQL migrations in order, seeds defaults.
- Creates initial director account.
- Writes `includes/config.php` and `includes/db.php`.
- Writes `storage/install.lock` to prevent re-install.
- Re-running setup after lock shows a blocked message unless lock is manually removed.

## 7) UI Baseline

- Theme direction follows the provided brown glass style.
- Default design tokens align to the supplied screens: earthy browns, glass surfaces, rounded corners, soft glow gradients.
- Installer UI follows this baseline for visual consistency while remaining lightweight for shared hosting.
