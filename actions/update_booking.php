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

$activeStatuses = ['confirmed', 'in_progress', 'awaiting_return', 'partially_returned'];
if (in_array($status, $activeStatuses, true)) {
    $itemsStmt = $mysqli->prepare('SELECT item_id, COALESCE(SUM(quantity),0) qty FROM booking_items WHERE tenant_id = ? AND booking_id = ? GROUP BY item_id');
    $itemsStmt->bind_param('ii', $tenantId, $bookingId);
    $itemsStmt->execute();
    $bookingItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();

    foreach ($bookingItems as $line) {
        $itemId = (int) ($line['item_id'] ?? 0);
        $requiredQty = (int) ($line['qty'] ?? 0);
        if ($itemId <= 0 || $requiredQty <= 0) {
            continue;
        }

        $stockStmt = $mysqli->prepare('SELECT item_name, quantity_in_store, quantity_hired_out FROM items WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stockStmt->bind_param('ii', $itemId, $tenantId);
        $stockStmt->execute();
        $stock = $stockStmt->get_result()->fetch_assoc();
        $stockStmt->close();
        if (!$stock) {
            flash('error', 'An item linked to this booking is missing.');
            action_redirect_back('modules/bookings/index.php');
        }

        $totalStock = (int) (($stock['quantity_in_store'] ?? 0) + ($stock['quantity_hired_out'] ?? 0));

        $reservedStmt = $mysqli->prepare('SELECT COALESCE(SUM(bi.quantity),0) qty
            FROM booking_items bi
            INNER JOIN bookings b ON b.id = bi.booking_id AND b.tenant_id = bi.tenant_id
            WHERE bi.tenant_id = ?
              AND bi.item_id = ?
              AND b.id <> ?
              AND (
                  (b.status = "confirmed" AND b.event_date = ?)
                  OR b.status IN ("in_progress", "awaiting_return", "partially_returned")
              )');
        $reservedStmt->bind_param('iiis', $tenantId, $itemId, $bookingId, $eventDate);
        $reservedStmt->execute();
        $reservedRow = $reservedStmt->get_result()->fetch_assoc();
        $reservedStmt->close();
        $reservedQty = (int) ($reservedRow['qty'] ?? 0);

        $availableQty = max($totalStock - $reservedQty, 0);
        if ($requiredQty > $availableQty) {
            flash('error', 'Cannot set booking to active. Item "' . (string) ($stock['item_name'] ?? 'Unknown') . '" is overbooked for the selected date.');
            action_redirect_back('modules/bookings/index.php');
        }
    }
}

$stmt = $mysqli->prepare('UPDATE bookings SET customer_id = ?, event_date = ?, event_location = ?, event_type = ?, notes = ?, status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
$stmt->bind_param('isssssii', $customerId, $eventDate, $eventLocation, $eventType, $notes, $status, $bookingId, $tenantId);
$stmt->execute();
$stmt->close();

audit_log('bookings', 'edit', 'bookings', $bookingId, null, ['customer_id' => $customerId, 'event_date' => $eventDate, 'status' => $status]);
flash('success', 'Booking updated.');
action_redirect_back('modules/bookings/index.php');
