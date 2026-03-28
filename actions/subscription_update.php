<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$tenantId = (int) post_str('tenant_id', '0');
$planId = (int) post_str('plan_id', '0');
$billingCycle = post_str('billing_cycle', 'monthly');
$changeMode = post_str('change_mode', 'immediate');

if ($tenantId <= 0 || $planId <= 0) {
    flash('error', 'Tenant and plan are required.');
    action_redirect_back('modules/subscriptions/index.php');
}

$mysqli = db();
$mysqli->begin_transaction();

try {
    $old = null;
    $stmt = $mysqli->prepare('SELECT id, plan_id FROM tenant_subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+30 days'));
    $stmt = $mysqli->prepare('INSERT INTO tenant_subscriptions (tenant_id, plan_id, billing_cycle, started_at, expires_at, grace_days, amount_due, amount_paid, outstanding_balance, subscription_status, auto_lock_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 7, 0, 0, 0, "active", 1, NOW(), NOW())');
    $stmt->bind_param('iisss', $tenantId, $planId, $billingCycle, $start, $end);
    $stmt->execute();
    $stmt->close();

    $directorId = (int) auth_user()['id'];
    $oldPlanId = $old ? (int) $old['plan_id'] : null;
    $stmt = $mysqli->prepare('INSERT INTO tenant_subscription_history (tenant_id, old_plan_id, new_plan_id, change_mode, changed_by_director_id, notes, changed_at) VALUES (?, ?, ?, ?, ?, "Manual update", NOW())');
    $stmt->bind_param('iiisi', $tenantId, $oldPlanId, $planId, $changeMode, $directorId);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();
    audit_log('subscriptions', 'edit', 'tenant_subscriptions', $tenantId, ['old_plan_id' => $oldPlanId], ['new_plan_id' => $planId, 'change_mode' => $changeMode]);
    flash('success', 'Subscription updated.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Subscription update failed: ' . $exception->getMessage());
}

action_redirect_back('modules/subscriptions/index.php');
