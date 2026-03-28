<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('messages.message');

$fromTenantId = (int) current_tenant_id();
$toTenantId = (int) post_str('to_tenant_id', '0');
$subject = post_str('subject');
$message = post_str('message');

if ($toTenantId <= 0 || $message === '') {
    flash('error', 'Recipient tenant and message are required.');
    action_redirect_back('modules/messages/index.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO tenant_messages (from_tenant_id, to_tenant_id, subject, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
$stmt->bind_param('iiss', $fromTenantId, $toTenantId, $subject, $message);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('messages', 'message', 'tenant_messages', $id, null, ['to_tenant_id' => $toTenantId]);
flash('success', 'Message sent.');
action_redirect_back('modules/messages/index.php');
