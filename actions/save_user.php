<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('users.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$countRes = db()->query('SELECT COUNT(*) c FROM tenant_users WHERE tenant_id = ' . $tenantId);
$currentCount = (int) (($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
enforceLimitForCurrentTenant('max_users', $currentCount, 'users');

$name = post_str('full_name');
$email = post_str('email');
$phone = post_str('phone');
$roleId = (int) post_str('role_id', '0');
$customRoleName = post_str('custom_role_name');
$password = (string) ($_POST['password'] ?? '');

if ($name === '' || $email === '' || strlen($password) < 8) {
    flash('error', 'Name, email and a minimum 8-character password are required.');
    action_redirect_back('modules/users/index.php');
}

$mysqli = db();

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

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $mysqli->prepare('INSERT INTO tenant_users (tenant_id, role_id, full_name, email, phone, password_hash, account_status, is_super_admin, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, "active", 0, NOW(), NOW())');
$stmt->bind_param('iissss', $tenantId, $roleId, $name, $email, $phone, $hash);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('users', 'create', 'tenant_users', $id, null, ['full_name' => $name, 'email' => $email]);
flash('success', 'User created.');
action_redirect_back('modules/users/index.php');
