# EventSaaS Master To-Do Checklist

Legend: `[ ]` pending, `[x]` complete

## 0. Foundation

- [x] Review full product requirements from `prompt.txt`
- [x] Review visual baseline from `screens/` and Terra glass design notes
- [x] Define default timezone: `Africa/Nairobi` (GMT+3)
- [x] Define default currency: `UGX`
- [x] Produce system architecture design document

## 1. Environment and Setup

- [x] Create browser-based installer for shared hosting (`setup.php`)
- [x] Add environment preflight checks (PHP version, mysqli, writable paths)
- [x] Add migration runner with ordered SQL execution
- [x] Add lock file mechanism (`storage/install.lock`) to prevent reinstall
- [x] Generate runtime config (`includes/config.php`) from installer input
- [x] Generate DB bootstrap file (`includes/db.php`) using mysqli
- [x] Create initial Director account during setup
- [x] Add migration tracking table (`migrations`)

## 2. Database Schema and Seeds

- [x] Create full base schema migration (`migrations/001_initial_schema.sql`)
- [x] Create seed migration (`migrations/002_seed_data.sql`)
- [x] Seed themes (10 required themes)
- [x] Seed status catalogs (tenant, subscription, booking)
- [x] Seed base permission catalog
- [x] Seed subscription plans: Basic, Intermediate, Pro
- [x] Seed plan features for gating engine

## 3. SaaS Governance Layer

- [x] Director authentication and dashboard
- [x] Tenant signup queue (pending approval)
- [x] Tenant approval/rejection workflow
- [x] Tenant lifecycle controls (active/suspended/rejected/locked)
- [x] Platform analytics for director
- [x] Director platform settings management

## 4. Subscription and Billing

- [x] Plan CRUD and pricing by cycle (monthly/quarterly/semiannual/annual)
- [x] Tenant subscription assignment and changes
- [x] Subscription state machine (trial, active, pending payment, overdue, suspended, locked, cancelled, expired)
- [x] Subscription history tracking
- [x] Billing invoice generation for tenants
- [x] Billing payment capture and balance calculation
- [x] Reminder scheduler/hooks and reminder logs
- [x] Manual lock/unlock by director
- [x] Auto-lock based on overdue + grace policy
- [x] Lock modes: soft lock and hard lock

## 5. Access and Accountability Layer

- [x] Login/logout with session security for all user types
- [x] Password hashing and reset flow
- [x] CSRF protection strategy across forms/actions
- [x] Tenant user management by super admin
- [x] RBAC role creation and maintenance
- [x] Role permission assignment
- [x] Optional user-level overrides
- [x] Route/action guard middleware functions
- [x] Full audit logging integration in write operations

## 6. Tenant Business Layer - Master Data

- [x] Customer management module
- [x] Inventory categories module
- [x] Item management module
- [x] Stock movement logging and adjustments
- [x] Services management module
- [x] External provider/source registry

## 7. Tenant Business Layer - Operations

- [x] Booking creation workflow (multi-step)
- [x] Booking status lifecycle tracking
- [x] Booking item allocation with stock checks
- [x] Service allocation in bookings
- [x] Worker assignment and dispatch tracking
- [x] Return processing workflow
- [x] Missing/damaged/lost declaration handling
- [x] Outsourced item return-to-owner flow
- [x] Worker accountability records

## 8. Finance Operations

- [x] Invoice create/edit/finalize/void workflow
- [x] Invoice preview and print view
- [x] PDF-ready rendering hooks
- [x] Receipt generation and print view
- [x] Partial payment support
- [x] Running balance and overdue reporting

## 9. Communication and Community

- [x] Internal tenant broadcasts and replies
- [x] Platform broadcasts (director to super admins)
- [x] Tenant-to-tenant messaging
- [x] Marketplace profile setup
- [x] Marketplace catalogue/listing management
- [x] Support tickets and threaded replies

## 10. Intelligence and Reporting

- [x] Calendar view for upcoming/overdue events
- [x] Notifications center and in-app alerts
- [x] Revenue and invoice collection reports
- [x] Inventory usage and outsourcing reports
- [x] Worker accountability reports
- [x] Marketplace engagement reports
- [x] Smart suggestion: frequently outsourced item purchase hint

## 11. Theming and UX

- [x] Tenant theme selector and persistence
- [x] Brown default theme and dark mode support
- [x] Up to 10 themes mapped to design tokens
- [x] Sidebar (expand/collapse + submenu behavior)
- [x] Responsive mobile drawer navigation
- [x] Consistent glassmorphism cards/forms/tables/modals

## 12. Testing and Hardening

- [x] Validate tenant isolation across all queries/actions
- [x] Validate feature gate checks before restricted actions
- [x] Validate plan limit checks against monthly usage stats
- [x] Validate account lock behavior (soft/hard)
- [x] Validate migration idempotency
- [x] Validate installer lock behavior and re-run protection

## 13. Current Milestone Snapshot

- [x] System design documented (`SYSTEM_DESIGN.md`)
- [x] Installer implemented (`setup.php`)
- [x] Full migration baseline created (`migrations/001_initial_schema.sql`)
- [x] Seed data added (`migrations/002_seed_data.sql`)
- [x] Application modules implementation in progress
