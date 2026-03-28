<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_auth();

$tenantId = current_tenant_id();
$category = post_str('category', 'general');
$priority = post_str('priority', 'medium');
$subject = post_str('subject');
$message = post_str('message');

if ($subject === '' || $message === '') {
    flash('error', 'Ticket subject and message are required.');
    action_redirect_back('modules/tickets/index.php');
}

if (!$tenantId && auth_role() !== 'director') {
    flash('error', 'Tenant context is required to open ticket.');
    action_redirect_back('modules/tickets/index.php');
}

$openedBy = (int) auth_user()['id'];
$tenant = $tenantId ? (int) $tenantId : 0;
$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO support_tickets (tenant_id, opened_by_user_id, category, priority, subject, message, ticket_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, "open", NOW(), NOW())');
$stmt->bind_param('iissss', $tenant, $openedBy, $category, $priority, $subject, $message);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('tickets', 'create', 'support_tickets', $id, null, ['priority' => $priority]);
flash('success', 'Support ticket created.');
action_redirect_back('modules/tickets/index.php');
