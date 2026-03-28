<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$tenantId = (int) post_str('tenant_id', '0');
$status = post_str('status', 'active');

if ($tenantId <= 0 || !in_array($status, ['active', 'rejected', 'suspended'], true)) {
    flash('error', 'Invalid tenant approval request.');
    action_redirect_back('modules/tenants/index.php');
}

$directorId = (int) auth_user()['id'];
$mysqli = db();
$stmt = $mysqli->prepare('UPDATE tenants SET account_status = ?, approved_by_director_id = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?');
$stmt->bind_param('sii', $status, $directorId, $tenantId);
$stmt->execute();
$stmt->close();

audit_log('tenants', 'approve', 'tenants', $tenantId, null, ['status' => $status]);
flash('success', 'Tenant status updated.');
action_redirect_back('modules/tenants/index.php');
