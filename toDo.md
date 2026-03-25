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

- [ ] Director authentication and dashboard
- [ ] Tenant signup queue (pending approval)
- [ ] Tenant approval/rejection workflow
- [ ] Tenant lifecycle controls (active/suspended/rejected/locked)
- [ ] Platform analytics for director
- [ ] Director platform settings management

## 4. Subscription and Billing

- [ ] Plan CRUD and pricing by cycle (monthly/quarterly/semiannual/annual)
- [ ] Tenant subscription assignment and changes
- [ ] Subscription state machine (trial, active, pending payment, overdue, suspended, locked, cancelled, expired)
- [ ] Subscription history tracking
- [ ] Billing invoice generation for tenants
- [ ] Billing payment capture and balance calculation
- [ ] Reminder scheduler/hooks and reminder logs
- [ ] Manual lock/unlock by director
- [ ] Auto-lock based on overdue + grace policy
- [ ] Lock modes: soft lock and hard lock

## 5. Access and Accountability Layer

- [ ] Login/logout with session security for all user types
- [ ] Password hashing and reset flow
- [ ] CSRF protection strategy across forms/actions
- [ ] Tenant user management by super admin
- [ ] RBAC role creation and maintenance
- [ ] Role permission assignment
- [ ] Optional user-level overrides
- [ ] Route/action guard middleware functions
- [ ] Full audit logging integration in write operations

## 6. Tenant Business Layer - Master Data

- [ ] Customer management module
- [ ] Inventory categories module
- [ ] Item management module
- [ ] Stock movement logging and adjustments
- [ ] Services management module
- [ ] External provider/source registry

## 7. Tenant Business Layer - Operations

- [ ] Booking creation workflow (multi-step)
- [ ] Booking status lifecycle tracking
- [ ] Booking item allocation with stock checks
- [ ] Service allocation in bookings
- [ ] Worker assignment and dispatch tracking
- [ ] Return processing workflow
- [ ] Missing/damaged/lost declaration handling
- [ ] Outsourced item return-to-owner flow
- [ ] Worker accountability records

## 8. Finance Operations

- [ ] Invoice create/edit/finalize/void workflow
- [ ] Invoice preview and print view
- [ ] PDF-ready rendering hooks
- [ ] Receipt generation and print view
- [ ] Partial payment support
- [ ] Running balance and overdue reporting

## 9. Communication and Community

- [ ] Internal tenant broadcasts and replies
- [ ] Platform broadcasts (director to super admins)
- [ ] Tenant-to-tenant messaging
- [ ] Marketplace profile setup
- [ ] Marketplace catalogue/listing management
- [ ] Support tickets and threaded replies

## 10. Intelligence and Reporting

- [ ] Calendar view for upcoming/overdue events
- [ ] Notifications center and in-app alerts
- [ ] Revenue and invoice collection reports
- [ ] Inventory usage and outsourcing reports
- [ ] Worker accountability reports
- [ ] Marketplace engagement reports
- [ ] Smart suggestion: frequently outsourced item purchase hint

## 11. Theming and UX

- [ ] Tenant theme selector and persistence
- [ ] Brown default theme and dark mode support
- [ ] Up to 10 themes mapped to design tokens
- [ ] Sidebar (expand/collapse + submenu behavior)
- [ ] Responsive mobile drawer navigation
- [ ] Consistent glassmorphism cards/forms/tables/modals

## 12. Testing and Hardening

- [ ] Validate tenant isolation across all queries/actions
- [ ] Validate feature gate checks before restricted actions
- [ ] Validate plan limit checks against monthly usage stats
- [ ] Validate account lock behavior (soft/hard)
- [ ] Validate migration idempotency
- [ ] Validate installer lock behavior and re-run protection

## 13. Current Milestone Snapshot

- [x] System design documented (`SYSTEM_DESIGN.md`)
- [x] Installer implemented (`setup.php`)
- [x] Full migration baseline created (`migrations/001_initial_schema.sql`)
- [x] Seed data added (`migrations/002_seed_data.sql`)
- [ ] Application modules implementation in progress
