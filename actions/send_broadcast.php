<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_auth();

$user = auth_user();
$subject = post_str('subject');
$message = post_str('message');

if ($subject === '' || $message === '') {
    flash('error', 'Subject and message are required.');
    action_redirect_back('modules/broadcasts/index.php');
}

$senderType = auth_role() === 'director' ? 'director' : 'super_admin';
$audienceType = $senderType === 'director' ? 'super_admins' : 'tenant_staff';
$tenantId = $senderType === 'director' ? null : (int) current_tenant_id();

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO broadcasts (tenant_id, sender_type, sender_id, subject, message, audience_type, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$senderId = (int) $user['id'];
$stmt->bind_param('isisss', $tenantId, $senderType, $senderId, $subject, $message, $audienceType);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('broadcasts', 'broadcast', 'broadcasts', $id, null, ['audience_type' => $audienceType]);
flash('success', 'Broadcast sent.');
action_redirect_back('modules/broadcasts/index.php');
