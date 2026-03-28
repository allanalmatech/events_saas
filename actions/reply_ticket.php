<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_auth();

$ticketId = (int) post_str('ticket_id', '0');
$message = post_str('message');

if ($ticketId <= 0 || $message === '') {
    flash('error', 'Ticket and reply message are required.');
    action_redirect_back('modules/tickets/index.php');
}

$tenantId = current_tenant_id();
$replyByType = auth_role() === 'director' ? 'director' : 'tenant_user';
$replyById = (int) auth_user()['id'];

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO ticket_replies (tenant_id, ticket_id, reply_by_type, reply_by_id, reply_message, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
$tenant = $tenantId ?? 0;
$stmt->bind_param('iisis', $tenant, $ticketId, $replyByType, $replyById, $message);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

$update = $mysqli->prepare('UPDATE support_tickets SET updated_at = NOW() WHERE id = ?');
$update->bind_param('i', $ticketId);
$update->execute();
$update->close();

audit_log('tickets', 'message', 'ticket_replies', $id, null, ['ticket_id' => $ticketId]);
flash('success', 'Reply posted.');
action_redirect_back('modules/tickets/index.php');
