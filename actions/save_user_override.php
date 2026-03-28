<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('permissions.assign');

$tenantId = (int) current_tenant_id();
$userId = (int) post_str('user_id', '0');
$permissionId = (int) post_str('permission_id', '0');
$grantType = post_str('grant_type', 'allow');

if ($userId <= 0 || $permissionId <= 0 || !in_array($grantType, ['allow', 'deny'], true)) {
    flash('error', 'Invalid user override payload.');
    action_redirect_back('modules/permissions/index.php');
}

$stmt = db()->prepare('INSERT INTO user_permissions (tenant_id, user_id, permission_id, grant_type) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE grant_type = VALUES(grant_type)');
$stmt->bind_param('iiis', $tenantId, $userId, $permissionId, $grantType);
$stmt->execute();
$stmt->close();

audit_log('permissions', 'assign', 'user_permissions', $userId, null, ['permission_id' => $permissionId, 'grant_type' => $grantType]);
flash('success', 'User override saved.');
action_redirect_back('modules/permissions/index.php');
