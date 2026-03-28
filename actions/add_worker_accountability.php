<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('bookings.assign');

$tenantId = (int) current_tenant_id();
$bookingId = (int) post_str('booking_id', '0');
$workerUserId = (int) post_str('worker_user_id', '0');
$issueType = post_str('issue_type', 'missing');
$quantity = (int) post_str('quantity', '1');
$chargeAmount = (float) post_str('charge_amount', '0');
$notes = post_str('notes');

if ($bookingId <= 0 || $workerUserId <= 0 || $quantity <= 0) {
    flash('error', 'Booking, worker and quantity are required.');
    action_redirect_back('modules/workers/index.php');
}

$stmt = db()->prepare('INSERT INTO worker_accountability (tenant_id, booking_id, worker_user_id, issue_type, quantity, charge_amount, notes, resolved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())');
$stmt->bind_param('iiisids', $tenantId, $bookingId, $workerUserId, $issueType, $quantity, $chargeAmount, $notes);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('workers', 'create', 'worker_accountability', $id, null, ['issue_type' => $issueType, 'quantity' => $quantity]);
flash('success', 'Worker accountability issue logged.');
action_redirect_back('modules/workers/index.php');
