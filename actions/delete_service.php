<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('services.delete');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$serviceId = (int) post_str('service_id', '0');

if ($serviceId <= 0) {
    flash('error', 'Invalid service selected.');
    action_redirect_back('modules/services/index.php');
}

$mysqli = db();
$serviceStmt = $mysqli->prepare('SELECT id, service_name, status, price, pricing_type, description FROM services WHERE id = ? AND tenant_id = ? LIMIT 1');
$serviceStmt->bind_param('ii', $serviceId, $tenantId);
$serviceStmt->execute();
$service = $serviceStmt->get_result()->fetch_assoc();
$serviceStmt->close();

if (!$service) {
    flash('error', 'Service not found.');
    action_redirect_back('modules/services/index.php');
}

$checks = [
    'booking_services' => 'SELECT COUNT(*) c FROM booking_services WHERE tenant_id = ? AND service_id = ?',
    'invoice_items' => 'SELECT COUNT(*) c FROM invoice_items WHERE tenant_id = ? AND line_type = "service" AND reference_id = ?',
];

$hasLinks = false;
foreach ($checks as $sql) {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        continue;
    }
    $stmt->bind_param('ii', $tenantId, $serviceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int) ($row['c'] ?? 0) > 0) {
        $hasLinks = true;
        break;
    }
}

if ($hasLinks) {
    $deactivate = $mysqli->prepare('UPDATE services SET status = "inactive", updated_at = NOW() WHERE id = ? AND tenant_id = ?');
    $deactivate->bind_param('ii', $serviceId, $tenantId);
    $deactivate->execute();
    $deactivate->close();

    audit_log('services', 'deactivate', 'services', $serviceId, $service, ['status' => 'inactive']);

    if (($service['status'] ?? '') === 'inactive') {
        flash('success', 'Service is already in use and remains inactive.');
    } else {
        flash('success', 'Service is linked to transactions and was deactivated instead of deleted.');
    }
    action_redirect_back('modules/services/index.php');
}

$delete = $mysqli->prepare('DELETE FROM services WHERE id = ? AND tenant_id = ? LIMIT 1');
$delete->bind_param('ii', $serviceId, $tenantId);

try {
    $delete->execute();
    $delete->close();
    audit_log('services', 'delete', 'services', $serviceId, $service, null);
    flash('success', 'Service deleted successfully.');
} catch (Throwable $exception) {
    if ($delete) {
        $delete->close();
    }

    $fallback = $mysqli->prepare('UPDATE services SET status = "inactive", updated_at = NOW() WHERE id = ? AND tenant_id = ?');
    if ($fallback) {
        $fallback->bind_param('ii', $serviceId, $tenantId);
        $fallback->execute();
        $fallback->close();
        audit_log('services', 'deactivate', 'services', $serviceId, $service, ['status' => 'inactive']);
    }

    flash('success', 'Service could not be deleted because it is linked to records and was deactivated instead.');
}

action_redirect_back('modules/services/index.php');
