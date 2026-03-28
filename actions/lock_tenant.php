<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$tenantId = (int) post_str('tenant_id', '0');
$mode = post_str('lock_mode', 'soft');
$reason = post_str('lock_reason', 'Outstanding subscription balance');
$amountDue = (float) post_str('amount_due', '0');
$dueDate = post_str('due_date', date('Y-m-d'));

if ($tenantId <= 0 || !in_array($mode, ['soft', 'hard'], true)) {
    flash('error', 'Invalid lock request.');
    action_redirect_back('modules/tenants/index.php');
}

$directorId = (int) auth_user()['id'];
$mysqli = db();
$mysqli->begin_transaction();

try {
    $off = $mysqli->prepare('UPDATE tenant_account_locks SET is_active = 0, unlocked_at = NOW() WHERE tenant_id = ? AND is_active = 1');
    $off->bind_param('i', $tenantId);
    $off->execute();
    $off->close();

    $grace = date('Y-m-d H:i:s', strtotime('+7 days'));
    $stmt = $mysqli->prepare('INSERT INTO tenant_account_locks (tenant_id, lock_mode, lock_reason, amount_due, due_date, grace_expires_at, is_active, locked_by_director_id, locked_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())');
    $stmt->bind_param('issdssi', $tenantId, $mode, $reason, $amountDue, $dueDate, $grace, $directorId);
    $stmt->execute();
    $lockId = (int) $stmt->insert_id;
    $stmt->close();

    $status = $mysqli->prepare('UPDATE tenants SET account_status = "locked", updated_at = NOW() WHERE id = ?');
    $status->bind_param('i', $tenantId);
    $status->execute();
    $status->close();

    $mysqli->commit();
    audit_log('tenants', 'lock', 'tenant_account_locks', $lockId, null, ['lock_mode' => $mode, 'amount_due' => $amountDue]);
    flash('success', 'Tenant locked successfully.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Lock failed: ' . $exception->getMessage());
}

action_redirect_back('modules/tenants/index.php');
