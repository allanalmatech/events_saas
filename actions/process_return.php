<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('returns.create');

$tenantId = (int) current_tenant_id();
$bookingId = (int) post_str('booking_id', '0');
$returnStatus = post_str('return_status', 'pending');
$notes = post_str('notes');
$bookingItemId = (int) post_str('booking_item_id', '0');
$qtySent = (int) post_str('qty_sent', '0');
$qtyReturned = (int) post_str('qty_returned', '0');
$qtyMissing = (int) post_str('qty_missing', '0');
$qtyDamaged = (int) post_str('qty_damaged', '0');
$markOwnerReturn = (int) post_str('mark_return_to_owner', '0');

if ($bookingId <= 0) {
    flash('error', 'Booking is required for return processing.');
    action_redirect_back('modules/returns/index.php');
}

$mysqli = db();
$processedBy = (int) auth_user()['id'];
$stmt = $mysqli->prepare('INSERT INTO returns (tenant_id, booking_id, processed_by, return_status, notes, processed_at) VALUES (?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('iiiss', $tenantId, $bookingId, $processedBy, $returnStatus, $notes);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

if ($bookingItemId > 0) {
    $ri = $mysqli->prepare('INSERT INTO return_items (tenant_id, return_id, booking_item_id, qty_sent, qty_returned, qty_missing, qty_damaged, mark_return_to_owner, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $ri->bind_param('iiiiiiiis', $tenantId, $id, $bookingItemId, $qtySent, $qtyReturned, $qtyMissing, $qtyDamaged, $markOwnerReturn, $notes);
    $ri->execute();
    $ri->close();
}

$update = $mysqli->prepare('UPDATE bookings SET status = CASE WHEN ? = "full" THEN "completed" WHEN ? = "partial" THEN "partially_returned" ELSE "awaiting_return" END, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
$update->bind_param('ssii', $returnStatus, $returnStatus, $bookingId, $tenantId);
$update->execute();
$update->close();

audit_log('returns', 'create', 'returns', $id, null, ['booking_id' => $bookingId, 'status' => $returnStatus, 'qty_missing' => $qtyMissing, 'qty_damaged' => $qtyDamaged]);
flash('success', 'Return record saved.');
action_redirect_back('modules/returns/index.php');
