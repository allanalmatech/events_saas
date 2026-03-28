<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('services.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$name = post_str('service_name');
$price = (float) post_str('price', '0');
$pricingType = post_str('pricing_type', 'flat');
$description = post_str('description');

if ($name === '') {
    flash('error', 'Service name is required.');
    action_redirect_back('modules/services/index.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO services (tenant_id, service_name, price, pricing_type, description, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "active", NOW(), NOW())');
$stmt->bind_param('isdss', $tenantId, $name, $price, $pricingType, $description);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('services', 'create', 'services', $id, null, ['service_name' => $name, 'price' => $price]);
flash('success', 'Service saved.');
action_redirect_back('modules/services/index.php');
