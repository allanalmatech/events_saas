<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('permissions.assign');

$tenantId = (int) current_tenant_id();
$userId = (int) post_str('user_id', '0');
$assigned = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids']) ? $_POST['assigned_role_ids'] : [];

if ($tenantId <= 0 || $userId <= 0) {
    flash('error', 'Invalid user role assignment request.');
    action_redirect_back('modules/users/index.php');
}

ensure_user_roles_table();

$mysqli = db();
$userStmt = $mysqli->prepare('SELECT id, role_id, is_super_admin FROM tenant_users WHERE tenant_id = ? AND id = ? LIMIT 1');
$userStmt->bind_param('ii', $tenantId, $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    flash('error', 'User not found.');
    action_redirect_back('modules/users/index.php');
}

$validRoleIds = [];
if ($assigned) {
    $cleanIds = array_values(array_unique(array_map('intval', $assigned)));
    $ids = implode(',', array_map('intval', $cleanIds));
    if ($ids !== '') {
        $res = $mysqli->query('SELECT id FROM roles WHERE tenant_id = ' . $tenantId . ' AND id IN (' . $ids . ')');
        while ($res && ($row = $res->fetch_assoc())) {
            $validRoleIds[] = (int) $row['id'];
        }
    }
}

$mysqli->begin_transaction();
try {
    $del = $mysqli->prepare('DELETE FROM user_roles WHERE tenant_id = ? AND user_id = ?');
    $del->bind_param('ii', $tenantId, $userId);
    $del->execute();
    $del->close();

    if ($validRoleIds) {
        $ins = $mysqli->prepare('INSERT INTO user_roles (tenant_id, user_id, role_id, created_at) VALUES (?, ?, ?, NOW())');
        foreach ($validRoleIds as $roleId) {
            $ins->bind_param('iii', $tenantId, $userId, $roleId);
            $ins->execute();
        }
        $ins->close();
    }

    if (!(int) $user['is_super_admin']) {
        $currentPrimary = (int) $user['role_id'];
        $newPrimary = null;
        if ($currentPrimary > 0 && in_array($currentPrimary, $validRoleIds, true)) {
            $newPrimary = $currentPrimary;
        } elseif ($validRoleIds) {
            $newPrimary = $validRoleIds[0];
        }

        $up = $mysqli->prepare('UPDATE tenant_users SET role_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
        $up->bind_param('iii', $newPrimary, $userId, $tenantId);
        $up->execute();
        $up->close();
    }

    $mysqli->commit();
    audit_log('users', 'assign', 'user_roles', $userId, null, ['role_ids' => $validRoleIds]);
    flash('success', 'User roles updated.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Could not assign user roles: ' . $exception->getMessage());
}

redirect('modules/users/index.php?user_id=' . $userId);
