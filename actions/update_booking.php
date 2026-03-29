<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('bookings.edit');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$bookingId = (int) post_str('booking_id', '0');
$customerId = (int) post_str('customer_id', '0');
$eventDate = post_str('event_date');
$eventLocation = post_str('event_location');
$eventType = post_str('event_type');
$notes = post_str('notes');
$status = post_str('status', 'draft');

if ($bookingId <= 0 || $customerId <= 0 || $eventDate === '') {
    flash('error', 'Booking, customer and event date are required.');
    action_redirect_back('modules/bookings/index.php');
}

if ($eventDate < date('Y-m-d')) {
    flash('error', 'Backdated booking dates are not allowed.');
    action_redirect_back('modules/bookings/index.php');
}

$allowedStatus = ['draft', 'confirmed', 'in_progress', 'awaiting_return', 'partially_returned', 'completed', 'cancelled'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'draft';
}

$mysqli = db();

$bookingCheck = $mysqli->prepare('SELECT id FROM bookings WHERE id = ? AND tenant_id = ? LIMIT 1');
$bookingCheck->bind_param('ii', $bookingId, $tenantId);
$bookingCheck->execute();
$booking = $bookingCheck->get_result()->fetch_assoc();
$bookingCheck->close();
if (!$booking) {
    flash('error', 'Booking not found for this tenant.');
    action_redirect_back('modules/bookings/index.php');
}

$customerCheck = $mysqli->prepare('SELECT id FROM customers WHERE id = ? AND tenant_id = ? LIMIT 1');
$customerCheck->bind_param('ii', $customerId, $tenantId);
$customerCheck->execute();
$customer = $customerCheck->get_result()->fetch_assoc();
$customerCheck->close();
if (!$customer) {
    flash('error', 'Selected customer is invalid.');
    action_redirect_back('modules/bookings/index.php');
}

$stmt = $mysqli->prepare('UPDATE bookings SET customer_id = ?, event_date = ?, event_location = ?, event_type = ?, notes = ?, status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
$stmt->bind_param('isssssii', $customerId, $eventDate, $eventLocation, $eventType, $notes, $status, $bookingId, $tenantId);
$stmt->execute();
$stmt->close();

audit_log('bookings', 'edit', 'bookings', $bookingId, null, ['customer_id' => $customerId, 'event_date' => $eventDate, 'status' => $status]);
flash('success', 'Booking updated.');
action_redirect_back('modules/bookings/index.php');
