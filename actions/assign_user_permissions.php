<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('permissions.assign');

$tenantId = (int) current_tenant_id();
$userId = (int) post_str('user_id', '0');
$assigned = isset($_POST['assigned_permission_ids']) && is_array($_POST['assigned_permission_ids']) ? $_POST['assigned_permission_ids'] : [];

if ($tenantId <= 0 || $userId <= 0) {
    flash('error', 'Invalid user permission assignment request.');
    action_redirect_back('modules/users/index.php');
}

$mysqli = db();

$userStmt = $mysqli->prepare('SELECT id FROM tenant_users WHERE tenant_id = ? AND id = ? LIMIT 1');
$userStmt->bind_param('ii', $tenantId, $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    flash('error', 'User not found.');
    action_redirect_back('modules/users/index.php');
}

$validPermissionIds = [];
if ($assigned) {
    $cleanIds = array_values(array_unique(array_map('intval', $assigned)));
    $ids = implode(',', array_map('intval', $cleanIds));
    if ($ids !== '') {
        $res = $mysqli->query('SELECT id FROM permissions WHERE id IN (' . $ids . ')');
        while ($res && ($row = $res->fetch_assoc())) {
            $validPermissionIds[] = (int) $row['id'];
        }
    }
}

$mysqli->begin_transaction();
try {
    $del = $mysqli->prepare('DELETE FROM user_permissions WHERE tenant_id = ? AND user_id = ?');
    $del->bind_param('ii', $tenantId, $userId);
    $del->execute();
    $del->close();

    if ($validPermissionIds) {
        $ins = $mysqli->prepare('INSERT INTO user_permissions (tenant_id, user_id, permission_id, grant_type) VALUES (?, ?, ?, "allow")');
        foreach ($validPermissionIds as $permissionId) {
            $ins->bind_param('iii', $tenantId, $userId, $permissionId);
            $ins->execute();
        }
        $ins->close();
    }

    $mysqli->commit();
    audit_log('permissions', 'assign', 'user_permissions', $userId, null, ['permission_ids' => $validPermissionIds]);
    flash('success', 'User permissions updated.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Could not assign user permissions: ' . $exception->getMessage());
}

redirect('modules/users/index.php');
