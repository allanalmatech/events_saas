<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('customers.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$countRes = db()->query('SELECT COUNT(*) c FROM customers WHERE tenant_id = ' . $tenantId);
$currentCount = (int) (($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
enforceLimitForCurrentTenant('max_customers', $currentCount, 'customers');

$fullName = post_str('full_name');
$phone = post_str('phone');
$email = post_str('email');
$address = post_str('address');
$notes = post_str('notes');

if ($fullName === '' || $phone === '') {
    flash('error', 'Customer name and phone are required.');
    action_redirect_back('modules/customers/index.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO customers (tenant_id, full_name, phone, email, address, notes, repeat_customer, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, "active", NOW(), NOW())');
$stmt->bind_param('isssss', $tenantId, $fullName, $phone, $email, $address, $notes);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('customers', 'create', 'customers', $id, null, ['full_name' => $fullName]);
flash('success', 'Customer added.');
action_redirect_back('modules/customers/index.php');
