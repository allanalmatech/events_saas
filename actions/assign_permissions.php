<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('permissions.assign');

$tenantId = (int) current_tenant_id();
$roleId = (int) post_str('role_id', '0');
$permissionIds = isset($_POST['permission_ids']) && is_array($_POST['permission_ids']) ? $_POST['permission_ids'] : [];

if ($roleId <= 0) {
    flash('error', 'Role is required.');
    action_redirect_back('modules/permissions/index.php');
}

$mysqli = db();
$mysqli->begin_transaction();

try {
    $del = $mysqli->prepare('DELETE FROM role_permissions WHERE tenant_id = ? AND role_id = ?');
    $del->bind_param('ii', $tenantId, $roleId);
    $del->execute();
    $del->close();

    if ($permissionIds) {
        $ins = $mysqli->prepare('INSERT INTO role_permissions (tenant_id, role_id, permission_id) VALUES (?, ?, ?)');
        foreach ($permissionIds as $pidRaw) {
            $pid = (int) $pidRaw;
            if ($pid <= 0) {
                continue;
            }
            $ins->bind_param('iii', $tenantId, $roleId, $pid);
            $ins->execute();
        }
        $ins->close();
    }

    $mysqli->commit();
    audit_log('permissions', 'assign', 'role_permissions', $roleId, null, ['permission_count' => count($permissionIds)]);
    flash('success', 'Role permissions updated.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Permission assignment failed: ' . $exception->getMessage());
}

action_redirect_back('modules/permissions/index.php');
