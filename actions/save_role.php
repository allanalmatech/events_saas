<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('roles.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$name = post_str('role_name');
$description = post_str('role_description');

if ($name === '') {
    flash('error', 'Role name is required.');
    action_redirect_back('modules/roles/index.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO roles (tenant_id, role_name, role_description, is_system_role, status, created_at, updated_at) VALUES (?, ?, ?, 0, "active", NOW(), NOW())');
$stmt->bind_param('iss', $tenantId, $name, $description);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('roles', 'create', 'roles', $id, null, ['role_name' => $name]);
flash('success', 'Role saved.');
action_redirect_back('modules/roles/index.php');
