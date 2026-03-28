<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();

$businessName = post_str('business_name');
$businessEmail = post_str('business_email');
$businessPhone = post_str('business_phone');
$businessTimezone = post_str('business_timezone', APP_TIMEZONE);
$ownerName = post_str('owner_name');
$ownerEmail = post_str('owner_email');
$ownerPassword = (string) ($_POST['owner_password'] ?? '');

if ($businessName === '' || $businessEmail === '' || $ownerName === '' || $ownerEmail === '' || strlen($ownerPassword) < 8) {
    flash('error', 'Please fill all required fields and use at least 8 characters for password.');
    redirect('register.php');
}

if (!filter_var($businessEmail, FILTER_VALIDATE_EMAIL) || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Invalid email format provided.');
    redirect('register.php');
}

if (!in_array($businessTimezone, timezone_identifiers_list(), true)) {
    $businessTimezone = APP_TIMEZONE;
}

$mysqli = db();
$mysqli->begin_transaction();

try {
    $stmt = $mysqli->prepare('INSERT INTO tenants (business_name, email, phone, currency_code, timezone, account_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "pending", NOW(), NOW())');
    $currency = APP_CURRENCY;
    $tz = $businessTimezone;
    $stmt->bind_param('sssss', $businessName, $businessEmail, $businessPhone, $currency, $tz);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not create tenant request.');
    }
    $tenantId = (int) $stmt->insert_id;
    $stmt->close();

    $stmt = $mysqli->prepare('INSERT INTO roles (tenant_id, role_name, role_description, is_system_role, status, created_at, updated_at) VALUES (?, "Super Admin", "Tenant owner", 1, "active", NOW(), NOW())');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $roleId = (int) $stmt->insert_id;
    $stmt->close();

    $hash = password_hash($ownerPassword, PASSWORD_BCRYPT);
    $stmt = $mysqli->prepare('INSERT INTO tenant_users (tenant_id, role_id, full_name, email, password_hash, account_status, is_super_admin, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "active", 1, NOW(), NOW())');
    $stmt->bind_param('iisss', $tenantId, $roleId, $ownerName, $ownerEmail, $hash);
    $stmt->execute();
    $ownerUserId = (int) $stmt->insert_id;
    $stmt->close();

    $stmt = $mysqli->prepare('INSERT INTO tenant_settings (tenant_id, active_theme_key, dark_mode_enabled, default_tax_percent, created_at, updated_at) VALUES (?, "brown_default", 0, 0.00, NOW(), NOW())');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT id FROM subscription_plans WHERE plan_key = "basic" LIMIT 1');
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($plan) {
        $planId = (int) $plan['id'];
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime('+14 days'));
        $stmt = $mysqli->prepare('INSERT INTO tenant_subscriptions (tenant_id, plan_id, billing_cycle, started_at, expires_at, grace_days, amount_due, amount_paid, outstanding_balance, subscription_status, auto_lock_enabled, created_at, updated_at) VALUES (?, ?, "monthly", ?, ?, 0, 0, 0, 0, "trial", 1, NOW(), NOW())');
        $stmt->bind_param('iiss', $tenantId, $planId, $start, $end);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();

    audit_log('auth', 'tenant_signup', 'tenants', $tenantId, null, ['owner_user_id' => $ownerUserId]);
    flash('success', 'Signup received. Your account is pending Director approval.');
    redirect('login.php');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Signup failed: ' . $exception->getMessage());
    redirect('register.php');
}
