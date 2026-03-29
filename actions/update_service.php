<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('services.edit');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$serviceId = (int) post_str('service_id', '0');
$name = post_str('service_name');
$price = (float) post_str('price', '0');
$pricingType = post_str('pricing_type', 'flat');
$status = post_str('status', 'active');
$description = post_str('description');

if ($serviceId <= 0 || $name === '' || $price < 0) {
    flash('error', 'Invalid service update payload.');
    action_redirect_back('modules/services/index.php');
}

if (!in_array($pricingType, ['flat', 'unit'], true)) {
    flash('error', 'Invalid pricing type selected.');
    action_redirect_back('modules/services/index.php');
}

if (!in_array($status, ['active', 'inactive'], true)) {
    flash('error', 'Invalid service status selected.');
    action_redirect_back('modules/services/index.php');
}

$mysqli = db();
$oldStmt = $mysqli->prepare('SELECT id, service_name, price, pricing_type, status, description FROM services WHERE id = ? AND tenant_id = ? LIMIT 1');
$oldStmt->bind_param('ii', $serviceId, $tenantId);
$oldStmt->execute();
$old = $oldStmt->get_result()->fetch_assoc();
$oldStmt->close();

if (!$old) {
    flash('error', 'Service not found for this tenant.');
    action_redirect_back('modules/services/index.php');
}

try {
    $stmt = $mysqli->prepare('UPDATE services SET service_name = ?, price = ?, pricing_type = ?, status = ?, description = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
    $stmt->bind_param('sdsssii', $name, $price, $pricingType, $status, $description, $serviceId, $tenantId);
    $stmt->execute();
    $stmt->close();

    audit_log('services', 'edit', 'services', $serviceId, $old, [
        'service_name' => $name,
        'price' => $price,
        'pricing_type' => $pricingType,
        'status' => $status,
        'description' => $description,
    ]);
    flash('success', 'Service updated successfully.');
} catch (Throwable $exception) {
    if (stripos($exception->getMessage(), 'uq_services_tenant_name') !== false) {
        flash('error', 'That service name is already in use for this tenant.');
    } else {
        flash('error', 'Could not update service: ' . $exception->getMessage());
    }
}

action_redirect_back('modules/services/index.php');
