<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('bookings.edit');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$bookingId = (int) post_str('booking_id', '0');
$serviceId = (int) post_str('service_id', '0');
$quantity = (int) post_str('quantity', '1');
$rate = (float) post_str('rate', '0');

if ($bookingId <= 0 || $serviceId <= 0 || $quantity <= 0) {
    flash('error', 'Booking, service and quantity are required.');
    action_redirect_back('modules/bookings/index.php');
}

$amount = $quantity * $rate;
$stmt = db()->prepare('INSERT INTO booking_services (tenant_id, booking_id, service_id, quantity, rate, amount, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('iiiidd', $tenantId, $bookingId, $serviceId, $quantity, $rate, $amount);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('bookings', 'assign', 'booking_services', $id, null, ['booking_id' => $bookingId, 'service_id' => $serviceId, 'quantity' => $quantity]);
flash('success', 'Service linked to booking.');
action_redirect_back('modules/bookings/index.php');
