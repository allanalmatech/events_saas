<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('bookings.delete');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$bookingId = (int) post_str('booking_id', '0');

if ($bookingId <= 0) {
    flash('error', 'Invalid booking selected for deletion.');
    action_redirect_back('modules/bookings/index.php');
}

$mysqli = db();

$existsStmt = $mysqli->prepare('SELECT id, booking_ref FROM bookings WHERE id = ? AND tenant_id = ? LIMIT 1');
$existsStmt->bind_param('ii', $bookingId, $tenantId);
$existsStmt->execute();
$booking = $existsStmt->get_result()->fetch_assoc();
$existsStmt->close();

if (!$booking) {
    flash('error', 'Booking not found.');
    action_redirect_back('modules/bookings/index.php');
}

$invStmt = $mysqli->prepare('SELECT COUNT(*) c FROM invoices WHERE tenant_id = ? AND booking_id = ?');
$invStmt->bind_param('ii', $tenantId, $bookingId);
$invStmt->execute();
$invCount = (int) (($invStmt->get_result()->fetch_assoc()['c']) ?? 0);
$invStmt->close();

$retStmt = $mysqli->prepare('SELECT COUNT(*) c FROM returns WHERE tenant_id = ? AND booking_id = ?');
$retStmt->bind_param('ii', $tenantId, $bookingId);
$retStmt->execute();
$retCount = (int) (($retStmt->get_result()->fetch_assoc()['c']) ?? 0);
$retStmt->close();

if ($invCount > 0 || $retCount > 0) {
    flash('error', 'Booking cannot be deleted because it is already involved in transactions.');
    action_redirect_back('modules/bookings/index.php');
}

$del = $mysqli->prepare('DELETE FROM bookings WHERE id = ? AND tenant_id = ? LIMIT 1');
$del->bind_param('ii', $bookingId, $tenantId);
$del->execute();
$del->close();

audit_log('bookings', 'delete', 'bookings', $bookingId, $booking, null);
flash('success', 'Booking deleted.');
action_redirect_back('modules/bookings/index.php');
