<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$tenantId = (int) post_str('tenant_id', '0');
$newStatus = post_str('new_status');

if ($tenantId <= 0 || $newStatus === '') {
    flash('error', 'Tenant and target status are required.');
    action_redirect_back('modules/subscriptions/index.php');
}

$mysqli = db();
$lookup = $mysqli->prepare('SELECT id FROM tenant_subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1');
$lookup->bind_param('i', $tenantId);
$lookup->execute();
$row = $lookup->get_result()->fetch_assoc();
$lookup->close();

$subscriptionId = (int) ($row['id'] ?? 0);
if ($subscriptionId <= 0) {
    flash('error', 'No subscription record found for the selected tenant.');
    action_redirect_back('modules/subscriptions/index.php');
}

$directorId = (int) auth_user()['id'];
$ok = set_subscription_status($subscriptionId, $newStatus, $directorId, 'Director manual status transition');
if (!$ok) {
    flash('error', 'Invalid status transition or update failure.');
    action_redirect_back('modules/subscriptions/index.php');
}

audit_log('subscriptions', 'edit', 'tenant_subscriptions', $subscriptionId, null, ['new_status' => $newStatus]);
flash('success', 'Subscription status updated.');
action_redirect_back('modules/subscriptions/index.php');
