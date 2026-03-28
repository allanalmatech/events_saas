<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('users.delete');
enforce_tenant_lock_for_write();

$actor = auth_user() ?: [];
$actorId = (int) ($actor['id'] ?? 0);
$actorIsSuper = !empty($actor['is_super_admin']);

$tenantId = (int) current_tenant_id();
$userId = (int) post_str('user_id', '0');

if ($userId <= 0) {
    flash('error', 'Invalid user selected for deletion.');
    action_redirect_back('modules/users/index.php');
}

if ($actorId > 0 && $userId === $actorId) {
    flash('error', 'You cannot delete your own account.');
    action_redirect_back('modules/users/index.php');
}

$mysqli = db();

$userStmt = $mysqli->prepare('SELECT id, full_name, account_status, is_super_admin FROM tenant_users WHERE tenant_id = ? AND id = ? LIMIT 1');
$userStmt->bind_param('ii', $tenantId, $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    flash('error', 'User not found.');
    action_redirect_back('modules/users/index.php');
}

$targetIsSuper = !empty($user['is_super_admin']);
if ($targetIsSuper && !$actorIsSuper) {
    flash('error', 'Only a super user can delete a super user.');
    action_redirect_back('modules/users/index.php');
}

$checks = [
    'bookings' => 'SELECT COUNT(*) c FROM bookings WHERE tenant_id = ? AND created_by = ?',
    'invoices' => 'SELECT COUNT(*) c FROM invoices WHERE tenant_id = ? AND created_by = ?',
    'payments' => 'SELECT COUNT(*) c FROM payments WHERE tenant_id = ? AND created_by = ?',
    'receipts' => 'SELECT COUNT(*) c FROM receipts WHERE tenant_id = ? AND received_by = ?',
    'returns' => 'SELECT COUNT(*) c FROM returns WHERE tenant_id = ? AND processed_by = ?',
    'item_stock_movements' => 'SELECT COUNT(*) c FROM item_stock_movements WHERE tenant_id = ? AND created_by = ?',
    'event_status_history' => 'SELECT COUNT(*) c FROM event_status_history WHERE tenant_id = ? AND changed_by = ?',
    'booking_workers' => 'SELECT COUNT(*) c FROM booking_workers WHERE tenant_id = ? AND worker_user_id = ?',
    'worker_accountability' => 'SELECT COUNT(*) c FROM worker_accountability WHERE tenant_id = ? AND worker_user_id = ?',
    'support_tickets' => 'SELECT COUNT(*) c FROM support_tickets WHERE tenant_id = ? AND opened_by_user_id = ?',
    'ticket_replies' => 'SELECT COUNT(*) c FROM ticket_replies WHERE tenant_id = ? AND reply_by_type = "tenant_user" AND reply_by_id = ?',
    'broadcast_replies' => 'SELECT COUNT(*) c FROM broadcast_replies WHERE tenant_id = ? AND replier_type = "tenant_user" AND replier_id = ?',
    'audit_logs' => 'SELECT COUNT(*) c FROM audit_logs WHERE tenant_id = ? AND actor_user_id = ?',
];

$hasLinks = false;
foreach ($checks as $sql) {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        continue;
    }
    $stmt->bind_param('ii', $tenantId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int) ($row['c'] ?? 0) > 0) {
        $hasLinks = true;
        break;
    }
}

if ($hasLinks) {
    $deactivate = $mysqli->prepare('UPDATE tenant_users SET account_status = "inactive", updated_at = NOW() WHERE id = ? AND tenant_id = ?');
    $deactivate->bind_param('ii', $userId, $tenantId);
    $deactivate->execute();
    $deactivate->close();

    audit_log('users', 'deactivate', 'tenant_users', $userId, $user, ['account_status' => 'inactive']);
    flash('success', 'User is linked to existing records and was deactivated instead of deleted.');
    action_redirect_back('modules/users/index.php');
}

$delete = $mysqli->prepare('DELETE FROM tenant_users WHERE id = ? AND tenant_id = ? LIMIT 1');
$delete->bind_param('ii', $userId, $tenantId);

try {
    $delete->execute();
    $delete->close();
    audit_log('users', 'delete', 'tenant_users', $userId, $user, null);
    flash('success', 'User deleted successfully.');
} catch (Throwable $exception) {
    if ($delete) {
        $delete->close();
    }
    $fallback = $mysqli->prepare('UPDATE tenant_users SET account_status = "inactive", updated_at = NOW() WHERE id = ? AND tenant_id = ?');
    if ($fallback) {
        $fallback->bind_param('ii', $userId, $tenantId);
        $fallback->execute();
        $fallback->close();
        audit_log('users', 'deactivate', 'tenant_users', $userId, $user, ['account_status' => 'inactive']);
    }
    flash('success', 'User could not be deleted because it is linked to records and was deactivated instead.');
}

action_redirect_back('modules/users/index.php');
