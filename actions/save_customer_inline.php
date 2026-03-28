<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json');

action_require_post();
require_tenant_user();
enforce_tenant_lock_for_write();

if (!can('customers.create') && !can('invoices.create')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

$tenantId = (int) current_tenant_id();
$fullName = post_str('full_name');
$phone = post_str('phone');
$email = post_str('email');
$address = post_str('address');
$notes = post_str('notes');

if ($fullName === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Customer name and phone are required.']);
    exit;
}

$countRes = db()->query('SELECT COUNT(*) c FROM customers WHERE tenant_id = ' . $tenantId);
$currentCount = (int) (($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
enforceLimitForCurrentTenant('max_customers', $currentCount, 'customers');

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO customers (tenant_id, full_name, phone, email, address, notes, repeat_customer, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, "active", NOW(), NOW())');
$stmt->bind_param('isssss', $tenantId, $fullName, $phone, $email, $address, $notes);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('customers', 'create', 'customers', $id, null, ['full_name' => $fullName]);

echo json_encode([
    'success' => true,
    'customer' => [
        'id' => $id,
        'full_name' => $fullName,
        'phone' => $phone,
    ],
]);
