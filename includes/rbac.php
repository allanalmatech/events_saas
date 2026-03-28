<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function ensure_user_roles_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $ensured = true;
    $sql = 'CREATE TABLE IF NOT EXISTS user_roles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        role_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_roles (tenant_id, user_id, role_id),
        KEY idx_user_roles_user (user_id),
        KEY idx_user_roles_role (role_id),
        CONSTRAINT fk_user_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES tenant_users(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        db()->query($sql);
    } catch (Throwable $exception) {
    }
}

function user_role_ids(int $tenantId, int $userId, int $primaryRoleId = 0): array
{
    ensure_user_roles_table();
    $roleIds = [];
    if ($primaryRoleId > 0) {
        $roleIds[] = $primaryRoleId;
    }

    $stmt = db()->prepare('SELECT role_id FROM user_roles WHERE tenant_id = ? AND user_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $tenantId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $roleIds[] = (int) $row['role_id'];
        }
        $stmt->close();
    }

    return array_values(array_unique(array_filter($roleIds)));
}

function can(string $permissionKey): bool
{
    $user = auth_user();
    if (!$user) {
        return false;
    }

    if (($user['role_type'] ?? '') === 'director') {
        return true;
    }

    if (!empty($user['is_super_admin'])) {
        return true;
    }

    $tenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : 0;
    $roleId = isset($user['role_id']) && $user['role_id'] !== null ? (int) $user['role_id'] : 0;
    $userId = (int) $user['id'];

    if ($tenantId <= 0) {
        return false;
    }

    $mysqli = db();

    $stmt = $mysqli->prepare('SELECT up.grant_type FROM user_permissions up INNER JOIN permissions p ON p.id = up.permission_id WHERE up.tenant_id = ? AND up.user_id = ? AND p.permission_key = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('iis', $tenantId, $userId, $permissionKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $override = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if ($override) {
            return $override['grant_type'] === 'allow';
        }
    }

    $roleIds = user_role_ids($tenantId, $userId, $roleId);
    if (!$roleIds) {
        return false;
    }

    $ids = implode(',', array_map('intval', $roleIds));
    $perm = $mysqli->real_escape_string($permissionKey);
    $sql = 'SELECT rp.id FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.tenant_id = ' . (int) $tenantId . ' AND rp.role_id IN (' . $ids . ') AND p.permission_key = "' . $perm . '" LIMIT 1';
    $res = $mysqli->query($sql);
    $allowed = $res && $res->num_rows > 0;

    return $allowed;
}

function require_permission(string $permissionKey): void
{
    if (!can($permissionKey)) {
        http_response_code(403);
        exit('Permission denied: ' . $permissionKey);
    }
}
