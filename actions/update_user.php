<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('users.edit');
enforce_tenant_lock_for_write();

$actor = auth_user() ?: [];
$actorId = (int) ($actor['id'] ?? 0);
$actorIsSuper = !empty($actor['is_super_admin']);

$tenantId = (int) current_tenant_id();
$userId = (int) post_str('user_id', '0');
$name = post_str('full_name');
$email = post_str('email');
$phone = post_str('phone');
$roleId = (int) post_str('role_id', '0');
$customRoleName = post_str('custom_role_name');
$status = post_str('account_status', 'active');

if ($userId <= 0 || $name === '' || $email === '') {
    flash('error', 'User, name and email are required.');
    action_redirect_back('modules/users/index.php');
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

$mysqli = db();

$userCheck = $mysqli->prepare('SELECT id, is_super_admin FROM tenant_users WHERE tenant_id = ? AND id = ? LIMIT 1');
$userCheck->bind_param('ii', $tenantId, $userId);
$userCheck->execute();
$existingUser = $userCheck->get_result()->fetch_assoc();
$userCheck->close();

if (!$existingUser) {
    flash('error', 'User not found for this tenant.');
    action_redirect_back('modules/users/index.php');
}

$targetIsSuper = !empty($existingUser['is_super_admin']);

if ($targetIsSuper && !$actorIsSuper) {
    flash('error', 'Only a super user can edit another super user.');
    action_redirect_back('modules/users/index.php');
}

if ($actorId > 0 && $userId === $actorId && $status !== 'active') {
    flash('error', 'You cannot deactivate your own account.');
    action_redirect_back('modules/users/index.php');
}

if ($roleId > 0) {
    $roleCheck = $mysqli->prepare('SELECT id FROM roles WHERE id = ? AND tenant_id = ? LIMIT 1');
    $roleCheck->bind_param('ii', $roleId, $tenantId);
    $roleCheck->execute();
    $roleExists = $roleCheck->get_result()->fetch_assoc();
    $roleCheck->close();

    if (!$roleExists) {
        flash('error', 'Selected role is invalid for this tenant.');
        action_redirect_back('modules/users/index.php');
    }
} else {
    if ($customRoleName === '') {
        flash('error', 'Select a role or enter a custom role name.');
        action_redirect_back('modules/users/index.php');
    }

    $findRole = $mysqli->prepare('SELECT id FROM roles WHERE tenant_id = ? AND role_name = ? LIMIT 1');
    $findRole->bind_param('is', $tenantId, $customRoleName);
    $findRole->execute();
    $existingRole = $findRole->get_result()->fetch_assoc();
    $findRole->close();

    if ($existingRole) {
        $roleId = (int) $existingRole['id'];
    } else {
        $newRole = $mysqli->prepare('INSERT INTO roles (tenant_id, role_name, role_description, is_system_role, status, created_at, updated_at) VALUES (?, ?, "Custom role", 0, "active", NOW(), NOW())');
        $newRole->bind_param('is', $tenantId, $customRoleName);
        $newRole->execute();
        $roleId = (int) $newRole->insert_id;
        $newRole->close();
    }
}

$stmt = $mysqli->prepare('UPDATE tenant_users SET role_id = ?, full_name = ?, email = ?, phone = ?, account_status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
$stmt->bind_param('issssii', $roleId, $name, $email, $phone, $status, $userId, $tenantId);

try {
    $stmt->execute();
    $stmt->close();
    audit_log('users', 'update', 'tenant_users', $userId, null, ['full_name' => $name, 'email' => $email, 'account_status' => $status]);
    flash('success', 'User updated.');
} catch (Throwable $exception) {
    if ($stmt) {
        $stmt->close();
    }
    if (stripos($exception->getMessage(), 'Duplicate') !== false) {
        flash('error', 'That email already exists.');
    } else {
        flash('error', 'Could not update user: ' . $exception->getMessage());
    }
}

action_redirect_back('modules/users/index.php');
