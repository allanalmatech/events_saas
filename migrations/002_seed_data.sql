INSERT INTO system_themes (theme_key, theme_name, is_dark, sort_order) VALUES
('brown_default', 'Brown Default', 0, 1),
('dark_terra', 'Dark Terra', 1, 2),
('blue_modern', 'Blue Modern', 0, 3),
('green_growth', 'Green Growth', 0, 4),
('gold_royal', 'Gold Royal', 0, 5),
('maroon_classic', 'Maroon Classic', 0, 6),
('purple_accent', 'Purple Accent', 0, 7),
('gray_professional', 'Gray Professional', 0, 8),
('teal_fresh', 'Teal Fresh', 0, 9),
('light_modern', 'Light Modern', 0, 10)
ON DUPLICATE KEY UPDATE theme_name = VALUES(theme_name), is_dark = VALUES(is_dark), sort_order = VALUES(sort_order);

INSERT INTO system_statuses (domain_key, status_key, status_label, sort_order) VALUES
('tenant_account', 'pending', 'Pending Approval', 1),
('tenant_account', 'active', 'Active', 2),
('tenant_account', 'suspended', 'Suspended', 3),
('tenant_account', 'rejected', 'Rejected', 4),
('tenant_account', 'locked', 'Locked', 5),
('subscription', 'trial', 'Trial', 1),
('subscription', 'active', 'Active', 2),
('subscription', 'pending_payment', 'Pending Payment', 3),
('subscription', 'overdue', 'Overdue', 4),
('subscription', 'suspended', 'Suspended', 5),
('subscription', 'locked', 'Locked', 6),
('subscription', 'cancelled', 'Cancelled', 7),
('subscription', 'expired', 'Expired', 8),
('booking', 'draft', 'Draft', 1),
('booking', 'confirmed', 'Confirmed', 2),
('booking', 'in_progress', 'In Progress', 3),
('booking', 'awaiting_return', 'Awaiting Return', 4),
('booking', 'partially_returned', 'Partially Returned', 5),
('booking', 'completed', 'Completed', 6),
('booking', 'cancelled', 'Cancelled', 7)
ON DUPLICATE KEY UPDATE status_label = VALUES(status_label), sort_order = VALUES(sort_order);

INSERT INTO permissions (permission_key, module_key, action_key, permission_label) VALUES
('dashboard.view', 'dashboard', 'view', 'View dashboard'),
('users.view', 'users', 'view', 'View users'),
('users.create', 'users', 'create', 'Create users'),
('users.edit', 'users', 'edit', 'Edit users'),
('users.delete', 'users', 'delete', 'Delete users'),
('users.approve', 'users', 'approve', 'Approve users'),
('roles.view', 'roles', 'view', 'View roles'),
('roles.create', 'roles', 'create', 'Create roles'),
('roles.edit', 'roles', 'edit', 'Edit roles'),
('roles.delete', 'roles', 'delete', 'Delete roles'),
('permissions.view', 'permissions', 'view', 'View permissions'),
('permissions.assign', 'permissions', 'assign', 'Assign permissions'),
('customers.view', 'customers', 'view', 'View customers'),
('customers.create', 'customers', 'create', 'Create customers'),
('customers.edit', 'customers', 'edit', 'Edit customers'),
('customers.delete', 'customers', 'delete', 'Delete customers'),
('items.view', 'items', 'view', 'View inventory items'),
('items.create', 'items', 'create', 'Create inventory items'),
('items.edit', 'items', 'edit', 'Edit inventory items'),
('items.delete', 'items', 'delete', 'Delete inventory items'),
('items.return', 'items', 'return', 'Process item returns'),
('services.view', 'services', 'view', 'View services'),
('services.create', 'services', 'create', 'Create services'),
('services.edit', 'services', 'edit', 'Edit services'),
('services.delete', 'services', 'delete', 'Delete services'),
('bookings.view', 'bookings', 'view', 'View bookings'),
('bookings.create', 'bookings', 'create', 'Create bookings'),
('bookings.edit', 'bookings', 'edit', 'Edit bookings'),
('bookings.delete', 'bookings', 'delete', 'Delete bookings'),
('bookings.assign', 'bookings', 'assign', 'Assign workers to bookings'),
('bookings.complete', 'bookings', 'mark_complete', 'Mark bookings complete'),
('returns.view', 'returns', 'view', 'View returns'),
('returns.create', 'returns', 'create', 'Process returns'),
('returns.edit', 'returns', 'edit', 'Edit returns'),
('returns.approve', 'returns', 'approve', 'Approve returns'),
('invoices.view', 'invoices', 'view', 'View invoices'),
('invoices.create', 'invoices', 'create', 'Create invoices'),
('invoices.edit', 'invoices', 'edit', 'Edit invoices'),
('invoices.delete', 'invoices', 'delete', 'Delete invoices'),
('invoices.print', 'invoices', 'print', 'Print invoices'),
('invoices.export', 'invoices', 'export', 'Export invoices as PDF'),
('receipts.view', 'receipts', 'view', 'View receipts'),
('receipts.create', 'receipts', 'create', 'Create receipts'),
('receipts.print', 'receipts', 'print', 'Print receipts'),
('receipts.export', 'receipts', 'export', 'Export receipts as PDF'),
('payments.view', 'payments', 'view', 'View payments'),
('payments.create', 'payments', 'create', 'Record payments'),
('payments.edit', 'payments', 'edit', 'Edit payments'),
('calendar.view', 'calendar', 'view', 'View calendar'),
('notifications.view', 'notifications', 'view', 'View notifications'),
('messages.view', 'messages', 'view', 'View internal messages'),
('messages.message', 'messages', 'message', 'Send internal messages'),
('broadcasts.view', 'broadcasts', 'view', 'View broadcasts'),
('broadcasts.create', 'broadcasts', 'broadcast', 'Create broadcasts'),
('broadcasts.reply', 'broadcasts', 'message', 'Reply to broadcasts'),
('marketplace.view', 'marketplace', 'view', 'View marketplace'),
('marketplace.create', 'marketplace', 'create', 'Create marketplace listings'),
('marketplace.edit', 'marketplace', 'edit', 'Edit marketplace listings'),
('marketplace.delete', 'marketplace', 'delete', 'Delete marketplace listings'),
('tickets.view', 'tickets', 'view', 'View support tickets'),
('tickets.create', 'tickets', 'create', 'Create support tickets'),
('tickets.reply', 'tickets', 'message', 'Reply to support tickets'),
('reports.view', 'reports', 'view_reports', 'View reports'),
('reports.export', 'reports', 'export', 'Export reports'),
('audit_logs.view', 'audit_logs', 'view', 'View audit logs'),
('settings.view', 'settings', 'view', 'View settings'),
('settings.edit', 'settings', 'edit', 'Edit settings'),
('subscriptions.view', 'subscriptions', 'view', 'View subscription details')
ON DUPLICATE KEY UPDATE module_key = VALUES(module_key), action_key = VALUES(action_key), permission_label = VALUES(permission_label);

INSERT INTO subscription_plans (
    plan_key, plan_name,
    price_monthly, price_quarterly, price_semiannual, price_annual,
    max_users, max_events_per_month, max_items, max_customers, max_marketplace_ads,
    has_calendar, has_reports, has_advanced_reports, has_marketplace, has_internal_messaging,
    has_ticketing, has_pdf_exports, has_broadcasts, has_audit_log_access, has_branding_customization,
    status
) VALUES
('basic', 'Basic', 70000, 200000, 390000, 740000, 5, 25, 80, 150, 5, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 'active'),
('intermediate', 'Intermediate', 150000, 420000, 820000, 1550000, 20, 120, 500, 1200, 40, 1, 1, 0, 1, 1, 1, 1, 1, 1, 1, 'active'),
('pro', 'Pro', 320000, 900000, 1760000, 3350000, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 'active')
ON DUPLICATE KEY UPDATE
plan_name = VALUES(plan_name),
price_monthly = VALUES(price_monthly),
price_quarterly = VALUES(price_quarterly),
price_semiannual = VALUES(price_semiannual),
price_annual = VALUES(price_annual),
max_users = VALUES(max_users),
max_events_per_month = VALUES(max_events_per_month),
max_items = VALUES(max_items),
max_customers = VALUES(max_customers),
max_marketplace_ads = VALUES(max_marketplace_ads),
has_calendar = VALUES(has_calendar),
has_reports = VALUES(has_reports),
has_advanced_reports = VALUES(has_advanced_reports),
has_marketplace = VALUES(has_marketplace),
has_internal_messaging = VALUES(has_internal_messaging),
has_ticketing = VALUES(has_ticketing),
has_pdf_exports = VALUES(has_pdf_exports),
has_broadcasts = VALUES(has_broadcasts),
has_audit_log_access = VALUES(has_audit_log_access),
has_branding_customization = VALUES(has_branding_customization),
status = VALUES(status);

INSERT INTO subscription_plan_features (plan_id, feature_key, feature_type, bool_value, int_value)
SELECT p.id, 'has_calendar', 'boolean', p.has_calendar, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_reports', 'boolean', p.has_reports, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_advanced_reports', 'boolean', p.has_advanced_reports, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_marketplace', 'boolean', p.has_marketplace, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_internal_messaging', 'boolean', p.has_internal_messaging, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_ticketing', 'boolean', p.has_ticketing, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_pdf_exports', 'boolean', p.has_pdf_exports, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_broadcasts', 'boolean', p.has_broadcasts, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_audit_log_access', 'boolean', p.has_audit_log_access, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'has_branding_customization', 'boolean', p.has_branding_customization, NULL FROM subscription_plans p
UNION ALL SELECT p.id, 'max_users', 'limit', NULL, p.max_users FROM subscription_plans p
UNION ALL SELECT p.id, 'max_events_per_month', 'limit', NULL, p.max_events_per_month FROM subscription_plans p
UNION ALL SELECT p.id, 'max_items', 'limit', NULL, p.max_items FROM subscription_plans p
UNION ALL SELECT p.id, 'max_customers', 'limit', NULL, p.max_customers FROM subscription_plans p
UNION ALL SELECT p.id, 'max_marketplace_ads', 'limit', NULL, p.max_marketplace_ads FROM subscription_plans p
ON DUPLICATE KEY UPDATE
feature_type = VALUES(feature_type),
bool_value = VALUES(bool_value),
int_value = VALUES(int_value);
