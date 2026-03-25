CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_themes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    theme_key VARCHAR(50) NOT NULL UNIQUE,
    theme_name VARCHAR(100) NOT NULL,
    is_dark TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_key VARCHAR(80) NOT NULL,
    status_key VARCHAR(80) NOT NULL,
    status_label VARCHAR(120) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_domain_status (domain_key, status_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(150) NOT NULL,
    business_initials VARCHAR(20) DEFAULT NULL,
    tagline VARCHAR(255) DEFAULT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address TEXT,
    logo_path VARCHAR(255) DEFAULT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'UGX',
    timezone VARCHAR(100) NOT NULL DEFAULT 'Africa/Nairobi',
    account_status ENUM('pending','active','suspended','rejected','locked') NOT NULL DEFAULT 'pending',
    approved_by_director_id INT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenants_email (email),
    KEY idx_tenants_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    active_theme_key VARCHAR(50) NOT NULL DEFAULT 'brown_default',
    dark_mode_enabled TINYINT(1) NOT NULL DEFAULT 0,
    invoice_footer TEXT,
    receipt_footer TEXT,
    default_tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    signature_path VARCHAR(255) DEFAULT NULL,
    stamp_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_settings_tenant (tenant_id),
    CONSTRAINT fk_tenant_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS director_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_director_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    role_description VARCHAR(255) DEFAULT NULL,
    is_system_role TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_tenant_name (tenant_id, role_name),
    CONSTRAINT fk_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED DEFAULT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    profile_image_path VARCHAR(255) DEFAULT NULL,
    address TEXT,
    emergency_contact VARCHAR(150) DEFAULT NULL,
    job_title VARCHAR(100) DEFAULT NULL,
    bio TEXT,
    account_status ENUM('pending','active','inactive','suspended') NOT NULL DEFAULT 'active',
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_users_email (tenant_id, email),
    KEY idx_tenant_users_role (role_id),
    CONSTRAINT fk_tenant_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(120) NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    action_key VARCHAR(50) NOT NULL,
    permission_label VARCHAR(160) NOT NULL,
    UNIQUE KEY uq_permissions_key (permission_key),
    KEY idx_permissions_module (module_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_role_permissions (tenant_id, role_id, permission_id),
    CONSTRAINT fk_role_permissions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    grant_type ENUM('allow','deny') NOT NULL DEFAULT 'allow',
    UNIQUE KEY uq_user_permissions (tenant_id, user_id, permission_id),
    CONSTRAINT fk_user_permissions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES tenant_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED DEFAULT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    actor_role VARCHAR(100) DEFAULT NULL,
    module_key VARCHAR(80) NOT NULL,
    action_key VARCHAR(80) NOT NULL,
    record_table VARCHAR(100) DEFAULT NULL,
    record_id VARCHAR(80) DEFAULT NULL,
    old_value LONGTEXT,
    new_value LONGTEXT,
    ip_address VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_tenant_created (tenant_id, created_at),
    KEY idx_audit_module_action (module_key, action_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    alt_phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address TEXT,
    notes TEXT,
    customer_type VARCHAR(50) DEFAULT NULL,
    repeat_customer TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customers_tenant (tenant_id),
    CONSTRAINT fk_customers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    category_name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_categories (tenant_id, category_name),
    KEY idx_item_categories_parent (parent_id),
    CONSTRAINT fk_item_categories_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_item_categories_parent FOREIGN KEY (parent_id) REFERENCES item_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    item_name VARCHAR(150) NOT NULL,
    sku VARCHAR(80) DEFAULT NULL,
    description TEXT,
    unit_type VARCHAR(50) DEFAULT NULL,
    quantity_total INT NOT NULL DEFAULT 0,
    quantity_hired_out INT NOT NULL DEFAULT 0,
    quantity_in_store INT NOT NULL DEFAULT 0,
    quantity_damaged INT NOT NULL DEFAULT 0,
    quantity_lost INT NOT NULL DEFAULT 0,
    reorder_threshold INT NOT NULL DEFAULT 0,
    owner_type ENUM('owned','external') NOT NULL DEFAULT 'owned',
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_items_tenant_sku (tenant_id, sku),
    KEY idx_items_tenant_name (tenant_id, item_name),
    CONSTRAINT fk_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES item_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    movement_type ENUM('add','hire_out','return','damage','loss','adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(80) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_stock_movements_item (item_id, created_at),
    CONSTRAINT fk_stock_movements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    pricing_type ENUM('flat','unit') NOT NULL DEFAULT 'flat',
    package_group VARCHAR(120) DEFAULT NULL,
    availability_notes VARCHAR(255) DEFAULT NULL,
    description TEXT,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_services_tenant_name (tenant_id, service_name),
    CONSTRAINT fk_services_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_ref VARCHAR(60) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    event_location VARCHAR(255) DEFAULT NULL,
    event_type VARCHAR(120) DEFAULT NULL,
    notes TEXT,
    status ENUM('draft','confirmed','in_progress','awaiting_return','partially_returned','completed','cancelled') NOT NULL DEFAULT 'draft',
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookings_ref (tenant_id, booking_ref),
    KEY idx_bookings_tenant_event_date (tenant_id, event_date),
    CONSTRAINT fk_bookings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    return_status ENUM('pending','partial','returned','damaged','lost') NOT NULL DEFAULT 'pending',
    sourced_externally TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_booking_items_booking (booking_id),
    CONSTRAINT fk_booking_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_items_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_booking_services_booking (booking_id),
    CONSTRAINT fk_booking_services_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_services_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_services_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_workers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    worker_user_id INT UNSIGNED NOT NULL,
    dispatch_at DATETIME DEFAULT NULL,
    handover_return_at DATETIME DEFAULT NULL,
    accountability_notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_booking_worker (booking_id, worker_user_id),
    CONSTRAINT fk_booking_workers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_workers_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_workers_user FOREIGN KEY (worker_user_id) REFERENCES tenant_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    processed_by INT UNSIGNED DEFAULT NULL,
    return_status ENUM('full','partial','pending','damaged','lost') NOT NULL DEFAULT 'pending',
    notes TEXT,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_returns_booking (booking_id),
    CONSTRAINT fk_returns_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_returns_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS return_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    return_id INT UNSIGNED NOT NULL,
    booking_item_id INT UNSIGNED NOT NULL,
    qty_sent INT NOT NULL,
    qty_returned INT NOT NULL DEFAULT 0,
    qty_missing INT NOT NULL DEFAULT 0,
    qty_damaged INT NOT NULL DEFAULT 0,
    mark_return_to_owner TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_return_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_return_items_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_return_items_booking_item FOREIGN KEY (booking_item_id) REFERENCES booking_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED DEFAULT NULL,
    customer_id INT UNSIGNED NOT NULL,
    invoice_no VARCHAR(80) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    invoice_status ENUM('draft','finalized','void') NOT NULL DEFAULT 'draft',
    terms TEXT,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoices_no (tenant_id, invoice_no),
    KEY idx_invoices_customer (customer_id),
    CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    line_type ENUM('service','item','external','custom') NOT NULL DEFAULT 'custom',
    reference_id INT UNSIGNED DEFAULT NULL,
    line_description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    receipt_no VARCHAR(80) NOT NULL,
    receipt_date DATE NOT NULL,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_reference VARCHAR(80) DEFAULT NULL,
    balance_after DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    received_by INT UNSIGNED DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_receipts_no (tenant_id, receipt_no),
    CONSTRAINT fk_receipts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_receipts_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    receipt_id INT UNSIGNED DEFAULT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_reference VARCHAR(80) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payments_invoice_date (invoice_id, payment_date),
    CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_receipt FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED DEFAULT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    channel_key ENUM('in_app','email_ready','sms_ready') NOT NULL DEFAULT 'in_app',
    reminder_type VARCHAR(80) DEFAULT NULL,
    related_table VARCHAR(80) DEFAULT NULL,
    related_id INT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    KEY idx_notifications_tenant_user (tenant_id, user_id, is_read),
    CONSTRAINT fk_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    opened_by_user_id INT UNSIGNED DEFAULT NULL,
    category VARCHAR(80) NOT NULL,
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    ticket_status ENUM('open','closed','escalated') NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_support_tickets_tenant_status (tenant_id, ticket_status),
    CONSTRAINT fk_support_tickets_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    ticket_id INT UNSIGNED NOT NULL,
    reply_by_type ENUM('director','tenant_user') NOT NULL,
    reply_by_id INT UNSIGNED DEFAULT NULL,
    reply_message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ticket_replies_ticket (ticket_id, created_at),
    CONSTRAINT fk_ticket_replies_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_replies_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS broadcasts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED DEFAULT NULL,
    sender_type ENUM('director','super_admin') NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    audience_type ENUM('tenant_staff','super_admins') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_broadcasts_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS broadcast_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED DEFAULT NULL,
    broadcast_id INT UNSIGNED NOT NULL,
    replier_type ENUM('director','tenant_user') NOT NULL,
    replier_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_broadcast_replies_broadcast (broadcast_id, created_at),
    CONSTRAINT fk_broadcast_replies_broadcast FOREIGN KEY (broadcast_id) REFERENCES broadcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    public_name VARCHAR(150) NOT NULL,
    about_text TEXT,
    contact_email VARCHAR(150) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    location_text VARCHAR(180) DEFAULT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketplace_profiles_tenant (tenant_id),
    CONSTRAINT fk_marketplace_profiles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_catalogue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    listing_type ENUM('service','item') NOT NULL,
    description TEXT,
    availability_status VARCHAR(80) DEFAULT NULL,
    media_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_marketplace_catalogue_tenant_active (tenant_id, is_active),
    CONSTRAINT fk_marketplace_catalogue_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_tenant_id INT UNSIGNED NOT NULL,
    to_tenant_id INT UNSIGNED NOT NULL,
    subject VARCHAR(180) DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    KEY idx_tenant_messages_to (to_tenant_id, is_read, created_at),
    CONSTRAINT fk_tenant_messages_from FOREIGN KEY (from_tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_messages_to FOREIGN KEY (to_tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outsourced_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED DEFAULT NULL,
    item_name VARCHAR(150) NOT NULL,
    provider_name VARCHAR(150) NOT NULL,
    provider_phone VARCHAR(50) DEFAULT NULL,
    source_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    quantity INT NOT NULL DEFAULT 1,
    return_to_owner_status ENUM('pending','returned') NOT NULL DEFAULT 'pending',
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_outsourced_items_tenant_status (tenant_id, return_to_owner_status),
    CONSTRAINT fk_outsourced_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_outsourced_items_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worker_accountability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    worker_user_id INT UNSIGNED NOT NULL,
    issue_type ENUM('missing','damaged','shortage') NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    charge_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT,
    resolved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_worker_accountability_tenant_resolved (tenant_id, resolved),
    CONSTRAINT fk_worker_accountability_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_worker_accountability_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_worker_accountability_user FOREIGN KEY (worker_user_id) REFERENCES tenant_users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(60) DEFAULT NULL,
    to_status VARCHAR(60) NOT NULL,
    changed_by INT UNSIGNED DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_event_status_history_booking (booking_id, changed_at),
    CONSTRAINT fk_event_status_history_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_status_history_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_key VARCHAR(50) NOT NULL UNIQUE,
    plan_name VARCHAR(100) NOT NULL,
    price_monthly DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    price_quarterly DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    price_semiannual DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    price_annual DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    max_users INT UNSIGNED DEFAULT NULL,
    max_events_per_month INT UNSIGNED DEFAULT NULL,
    max_items INT UNSIGNED DEFAULT NULL,
    max_customers INT UNSIGNED DEFAULT NULL,
    max_marketplace_ads INT UNSIGNED DEFAULT NULL,
    has_calendar TINYINT(1) NOT NULL DEFAULT 0,
    has_reports TINYINT(1) NOT NULL DEFAULT 0,
    has_advanced_reports TINYINT(1) NOT NULL DEFAULT 0,
    has_marketplace TINYINT(1) NOT NULL DEFAULT 0,
    has_internal_messaging TINYINT(1) NOT NULL DEFAULT 0,
    has_ticketing TINYINT(1) NOT NULL DEFAULT 1,
    has_pdf_exports TINYINT(1) NOT NULL DEFAULT 0,
    has_broadcasts TINYINT(1) NOT NULL DEFAULT 0,
    has_audit_log_access TINYINT(1) NOT NULL DEFAULT 0,
    has_branding_customization TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plan_features (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    feature_type ENUM('boolean','limit') NOT NULL DEFAULT 'boolean',
    bool_value TINYINT(1) DEFAULT NULL,
    int_value INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_plan_features (plan_id, feature_key),
    CONSTRAINT fk_subscription_plan_features_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    billing_cycle ENUM('monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',
    started_at DATE NOT NULL,
    expires_at DATE NOT NULL,
    grace_days INT UNSIGNED NOT NULL DEFAULT 0,
    amount_due DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    outstanding_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subscription_status ENUM('trial','active','pending_payment','overdue','suspended','locked','cancelled','expired') NOT NULL DEFAULT 'trial',
    auto_lock_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tenant_subscriptions_tenant_status (tenant_id, subscription_status),
    CONSTRAINT fk_tenant_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_subscription_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    old_plan_id INT UNSIGNED DEFAULT NULL,
    new_plan_id INT UNSIGNED DEFAULT NULL,
    change_mode ENUM('immediate','next_cycle') NOT NULL DEFAULT 'immediate',
    changed_by_director_id INT UNSIGNED DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant_subscription_history_tenant (tenant_id, changed_at),
    CONSTRAINT fk_tenant_subscription_history_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_subscription_history_old_plan FOREIGN KEY (old_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL,
    CONSTRAINT fk_tenant_subscription_history_new_plan FOREIGN KEY (new_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_billing_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED DEFAULT NULL,
    invoice_number VARCHAR(80) NOT NULL,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,
    billing_cycle ENUM('monthly','quarterly','semiannual','annual') NOT NULL,
    amount_charged DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_date DATE NOT NULL,
    payment_status ENUM('unpaid','partial','paid','overdue') NOT NULL DEFAULT 'unpaid',
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_billing_invoice_number (invoice_number),
    KEY idx_tenant_billing_invoices_tenant_status (tenant_id, payment_status),
    CONSTRAINT fk_tenant_billing_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_billing_invoices_subscription FOREIGN KEY (subscription_id) REFERENCES tenant_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_billing_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    billing_invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_reference VARCHAR(80) DEFAULT NULL,
    payment_date DATE NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant_billing_payments_invoice (billing_invoice_id),
    CONSTRAINT fk_tenant_billing_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_billing_payments_invoice FOREIGN KEY (billing_invoice_id) REFERENCES tenant_billing_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_billing_reminders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    billing_invoice_id INT UNSIGNED DEFAULT NULL,
    reminder_type ENUM('before_due','due_date','after_due','grace_period','before_lock','after_lock') NOT NULL,
    channel_key ENUM('in_app','email_ready','sms_ready') NOT NULL DEFAULT 'in_app',
    reminder_message VARCHAR(255) DEFAULT NULL,
    tenant_response VARCHAR(255) DEFAULT NULL,
    follow_up_state VARCHAR(80) DEFAULT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant_billing_reminders_tenant_sent (tenant_id, sent_at),
    CONSTRAINT fk_tenant_billing_reminders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_billing_reminders_invoice FOREIGN KEY (billing_invoice_id) REFERENCES tenant_billing_invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_feature_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    override_type ENUM('boolean','limit') NOT NULL,
    bool_value TINYINT(1) DEFAULT NULL,
    int_value INT DEFAULT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    starts_at DATETIME DEFAULT NULL,
    ends_at DATETIME DEFAULT NULL,
    created_by_director_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_feature_overrides (tenant_id, feature_key),
    CONSTRAINT fk_tenant_feature_overrides_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_account_locks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    lock_mode ENUM('soft','hard') NOT NULL DEFAULT 'soft',
    lock_reason VARCHAR(255) DEFAULT NULL,
    amount_due DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    due_date DATE DEFAULT NULL,
    grace_expires_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    locked_by_director_id INT UNSIGNED DEFAULT NULL,
    locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    unlocked_at DATETIME DEFAULT NULL,
    KEY idx_tenant_account_locks_tenant_active (tenant_id, is_active),
    CONSTRAINT fk_tenant_account_locks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_plan_usage_stats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    stat_month CHAR(7) NOT NULL,
    users_count INT UNSIGNED NOT NULL DEFAULT 0,
    bookings_count INT UNSIGNED NOT NULL DEFAULT 0,
    invoices_count INT UNSIGNED NOT NULL DEFAULT 0,
    receipts_count INT UNSIGNED NOT NULL DEFAULT 0,
    items_count INT UNSIGNED NOT NULL DEFAULT 0,
    customers_count INT UNSIGNED NOT NULL DEFAULT 0,
    marketplace_ads_count INT UNSIGNED NOT NULL DEFAULT 0,
    storage_used_mb DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_plan_usage_stats (tenant_id, stat_month),
    CONSTRAINT fk_tenant_plan_usage_stats_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
