<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('bookings.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$month = date('Y-m');
$countRes = db()->query("SELECT COUNT(*) c FROM bookings WHERE tenant_id = {$tenantId} AND DATE_FORMAT(created_at, '%Y-%m') = '" . db()->real_escape_string($month) . "'");
$currentCount = (int) (($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
enforceLimitForCurrentTenant('max_events_per_month', $currentCount, 'bookings');

$customerId = (int) post_str('customer_id', '0');
$eventDate = post_str('event_date');
$eventLocation = post_str('event_location');
$eventType = post_str('event_type');
$notes = post_str('notes');

if ($customerId <= 0 || $eventDate === '') {
    flash('error', 'Customer and event date are required.');
    action_redirect_back('modules/bookings/index.php');
}

if (strtotime($eventDate) < strtotime(date('Y-m-d'))) {
    flash('error', 'Backdated bookings are not allowed.');
    action_redirect_back('modules/bookings/index.php');
}

$bookingRef = 'BK-' . date('Ymd') . '-' . strtoupper(substr(md5((string) microtime(true)), 0, 6));
$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO bookings (tenant_id, booking_ref, customer_id, event_date, event_location, event_type, notes, status, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, "draft", ?, NOW(), NOW())');
$createdBy = (int) auth_user()['id'];
$stmt->bind_param('isissssi', $tenantId, $bookingRef, $customerId, $eventDate, $eventLocation, $eventType, $notes, $createdBy);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('bookings', 'create', 'bookings', $id, null, ['booking_ref' => $bookingRef]);
flash('success', 'Booking created as draft.');
action_redirect_back('modules/bookings/index.php');
