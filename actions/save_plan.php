<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$planId = (int) post_str('plan_id', '0');
$planKey = post_str('plan_key');
$planName = post_str('plan_name');
$monthly = (float) post_str('price_monthly', '0');
$quarterly = (float) post_str('price_quarterly', '0');
$semiannual = (float) post_str('price_semiannual', '0');
$annual = (float) post_str('price_annual', '0');
$maxUsers = post_str('max_users');
$maxEvents = post_str('max_events_per_month');
$status = post_str('status', 'active');

if ($planKey === '' || $planName === '') {
    flash('error', 'Plan key and plan name are required.');
    action_redirect_back('modules/plans/index.php');
}

$maxUsersVal = $maxUsers === '' ? null : (int) $maxUsers;
$maxEventsVal = $maxEvents === '' ? null : (int) $maxEvents;

$mysqli = db();
if ($planId > 0) {
    $stmt = $mysqli->prepare('UPDATE subscription_plans SET plan_key = ?, plan_name = ?, price_monthly = ?, price_quarterly = ?, price_semiannual = ?, price_annual = ?, max_users = ?, max_events_per_month = ?, status = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('ssddddiisi', $planKey, $planName, $monthly, $quarterly, $semiannual, $annual, $maxUsersVal, $maxEventsVal, $status, $planId);
    $stmt->execute();
    $stmt->close();
    audit_log('plans', 'edit', 'subscription_plans', $planId, null, ['plan_key' => $planKey, 'plan_name' => $planName]);
    flash('success', 'Plan updated.');
} else {
    $stmt = $mysqli->prepare('INSERT INTO subscription_plans (plan_key, plan_name, price_monthly, price_quarterly, price_semiannual, price_annual, max_users, max_events_per_month, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('ssddddiis', $planKey, $planName, $monthly, $quarterly, $semiannual, $annual, $maxUsersVal, $maxEventsVal, $status);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();
    audit_log('plans', 'create', 'subscription_plans', $id, null, ['plan_key' => $planKey, 'plan_name' => $planName]);
    flash('success', 'Plan created.');
}

action_redirect_back('modules/plans/index.php');
