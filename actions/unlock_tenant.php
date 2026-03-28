<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$tenantId = (int) post_str('tenant_id', '0');

if ($tenantId <= 0) {
    flash('error', 'Invalid tenant for unlock.');
    action_redirect_back('modules/tenants/index.php');
}

$mysqli = db();
$mysqli->begin_transaction();

try {
    $stmt = $mysqli->prepare('UPDATE tenant_account_locks SET is_active = 0, unlocked_at = NOW() WHERE tenant_id = ? AND is_active = 1');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare('UPDATE tenants SET account_status = "active", updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();
    audit_log('tenants', 'unlock', 'tenants', $tenantId, null, ['status' => 'active']);
    flash('success', 'Tenant unlocked.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Unlock failed: ' . $exception->getMessage());
}

action_redirect_back('modules/tenants/index.php');
