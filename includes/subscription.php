<?php
declare(strict_types=1);

require_once __DIR__ . '/feature_limits.php';

function tenant_subscription(int $tenantId): ?array
{
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT ts.*, sp.plan_name, sp.plan_key FROM tenant_subscriptions ts INNER JOIN subscription_plans sp ON sp.id = ts.plan_id WHERE ts.tenant_id = ? ORDER BY ts.id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function subscription_is_overdue(int $tenantId): bool
{
    $sub = tenant_subscription($tenantId);
    if (!$sub) {
        return false;
    }
    return in_array($sub['subscription_status'], ['overdue', 'locked', 'suspended', 'expired'], true);
}

function run_subscription_automation(): array
{
    $mysqli = db();
    $result = [
        'overdue_marked' => 0,
        'locked' => 0,
    ];

    $mysqli->query('UPDATE tenant_subscriptions SET subscription_status = "overdue", updated_at = NOW() WHERE expires_at < CURDATE() AND subscription_status IN ("trial","active","pending_payment")');
    $result['overdue_marked'] = $mysqli->affected_rows;

    $sql = 'SELECT ts.id, ts.tenant_id, ts.outstanding_balance, DATE_ADD(ts.expires_at, INTERVAL ts.grace_days DAY) AS grace_end FROM tenant_subscriptions ts WHERE ts.subscription_status = "overdue" AND ts.auto_lock_enabled = 1';
    $rowsRes = $mysqli->query($sql);
    $rows = $rowsRes ? $rowsRes->fetch_all(MYSQLI_ASSOC) : [];

    foreach ($rows as $row) {
        $tenantId = (int) $row['tenant_id'];
        $graceEnd = $row['grace_end'];
        if (strtotime($graceEnd) >= strtotime(date('Y-m-d'))) {
            continue;
        }

        $check = $mysqli->prepare('SELECT id FROM tenant_account_locks WHERE tenant_id = ? AND is_active = 1 LIMIT 1');
        $check->bind_param('i', $tenantId);
        $check->execute();
        $active = $check->get_result()->fetch_assoc();
        $check->close();
        if ($active) {
            continue;
        }

        $dueDate = date('Y-m-d', strtotime($graceEnd));
        $graceExpires = date('Y-m-d H:i:s', strtotime($graceEnd));
        $amountDue = (float) $row['outstanding_balance'];

        $ins = $mysqli->prepare('INSERT INTO tenant_account_locks (tenant_id, lock_mode, lock_reason, amount_due, due_date, grace_expires_at, is_active, locked_at) VALUES (?, "soft", "Auto lock after overdue grace", ?, ?, ?, 1, NOW())');
        $ins->bind_param('idss', $tenantId, $amountDue, $dueDate, $graceExpires);
        $ins->execute();
        $ins->close();

        $upTenant = $mysqli->prepare('UPDATE tenants SET account_status = "locked", updated_at = NOW() WHERE id = ?');
        $upTenant->bind_param('i', $tenantId);
        $upTenant->execute();
        $upTenant->close();

        $upSub = $mysqli->prepare('UPDATE tenant_subscriptions SET subscription_status = "locked", updated_at = NOW() WHERE id = ?');
        $subId = (int) $row['id'];
        $upSub->bind_param('i', $subId);
        $upSub->execute();
        $upSub->close();

        $result['locked']++;
    }

    return $result;
}

function subscription_allowed_transitions(): array
{
    return [
        'trial' => ['active', 'pending_payment', 'cancelled', 'expired'],
        'active' => ['pending_payment', 'overdue', 'suspended', 'cancelled', 'expired', 'locked'],
        'pending_payment' => ['active', 'overdue', 'cancelled', 'locked'],
        'overdue' => ['active', 'locked', 'suspended', 'cancelled', 'expired'],
        'suspended' => ['active', 'locked', 'cancelled'],
        'locked' => ['active', 'suspended', 'cancelled'],
        'cancelled' => ['active'],
        'expired' => ['active'],
    ];
}

function can_transition_subscription_status(string $from, string $to): bool
{
    $map = subscription_allowed_transitions();
    if (!isset($map[$from])) {
        return false;
    }
    return in_array($to, $map[$from], true);
}

function set_subscription_status(int $subscriptionId, string $newStatus, int $directorId, string $note = 'Manual status update'): bool
{
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT id, tenant_id, plan_id, subscription_status FROM tenant_subscriptions WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $subscriptionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }

    $oldStatus = (string) $row['subscription_status'];
    if (!can_transition_subscription_status($oldStatus, $newStatus)) {
        return false;
    }

    $tenantId = (int) $row['tenant_id'];
    $planId = (int) $row['plan_id'];

    $mysqli->begin_transaction();
    try {
        $up = $mysqli->prepare('UPDATE tenant_subscriptions SET subscription_status = ?, updated_at = NOW() WHERE id = ?');
        $up->bind_param('si', $newStatus, $subscriptionId);
        $up->execute();
        $up->close();

        $oldPlanId = $planId;
        $newPlanId = $planId;
        $history = $mysqli->prepare('INSERT INTO tenant_subscription_history (tenant_id, old_plan_id, new_plan_id, change_mode, changed_by_director_id, notes, changed_at) VALUES (?, ?, ?, "immediate", ?, ?, NOW())');
        $history->bind_param('iiiis', $tenantId, $oldPlanId, $newPlanId, $directorId, $note);
        $history->execute();
        $history->close();

        $tenantStatus = $newStatus === 'locked' ? 'locked' : 'active';
        $ten = $mysqli->prepare('UPDATE tenants SET account_status = ?, updated_at = NOW() WHERE id = ?');
        $ten->bind_param('si', $tenantStatus, $tenantId);
        $ten->execute();
        $ten->close();

        $mysqli->commit();
        return true;
    } catch (Throwable $exception) {
        $mysqli->rollback();
        return false;
    }
}
