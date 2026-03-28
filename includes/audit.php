<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function audit_log(string $moduleKey, string $actionKey, ?string $recordTable = null, $recordId = null, $oldValue = null, $newValue = null): void
{
    $mysqli = db_try();
    if (!$mysqli) {
        return;
    }

    $user = auth_user();
    $tenantId = $user['tenant_id'] ?? null;
    $actorUserId = $user['id'] ?? null;
    $actorRole = $user['role_type'] ?? 'guest';
    $recordIdStr = $recordId === null ? null : (string) $recordId;
    $oldJson = $oldValue === null ? null : json_encode($oldValue);
    $newJson = $newValue === null ? null : json_encode($newValue);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $mysqli->prepare('INSERT INTO audit_logs (tenant_id, actor_user_id, actor_role, module_key, action_key, record_table, record_id, old_value, new_value, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'iisssssssss',
        $tenantId,
        $actorUserId,
        $actorRole,
        $moduleKey,
        $actionKey,
        $recordTable,
        $recordIdStr,
        $oldJson,
        $newJson,
        $ip,
        $agent
    );
    $stmt->execute();
    $stmt->close();
}
