<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('bookings.edit');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$bookingId = (int) post_str('booking_id', '0');
$itemId = (int) post_str('item_id', '0');
$quantity = (int) post_str('quantity', '0');
$rate = (float) post_str('rate', '0');

if ($bookingId <= 0 || $itemId <= 0 || $quantity <= 0) {
    flash('error', 'Booking, item, and quantity are required.');
    action_redirect_back('modules/bookings/index.php');
}

$mysqli = db();
$stockStmt = $mysqli->prepare('SELECT quantity_in_store FROM items WHERE id = ? AND tenant_id = ? LIMIT 1');
$stockStmt->bind_param('ii', $itemId, $tenantId);
$stockStmt->execute();
$stockRow = $stockStmt->get_result()->fetch_assoc();
$stockStmt->close();

$available = (int) ($stockRow['quantity_in_store'] ?? 0);
if ($quantity > $available) {
    flash('error', 'Requested quantity exceeds available stock.');
    action_redirect_back('modules/bookings/index.php');
}

$amount = $quantity * $rate;
$mysqli->begin_transaction();
try {
    $insert = $mysqli->prepare('INSERT INTO booking_items (tenant_id, booking_id, item_id, quantity, rate, amount, return_status, sourced_externally, created_at) VALUES (?, ?, ?, ?, ?, ?, "pending", 0, NOW())');
    $insert->bind_param('iiiidd', $tenantId, $bookingId, $itemId, $quantity, $rate, $amount);
    $insert->execute();
    $lineId = (int) $insert->insert_id;
    $insert->close();

    $stock = $mysqli->prepare('UPDATE items SET quantity_hired_out = quantity_hired_out + ?, quantity_in_store = quantity_in_store - ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
    $stock->bind_param('iiii', $quantity, $quantity, $itemId, $tenantId);
    $stock->execute();
    $stock->close();

    $move = $mysqli->prepare('INSERT INTO item_stock_movements (tenant_id, item_id, movement_type, quantity, reference_type, reference_id, notes, created_at) VALUES (?, ?, "hire_out", ?, "booking", ?, "Booking allocation", NOW())');
    $move->bind_param('iiii', $tenantId, $itemId, $quantity, $bookingId);
    $move->execute();
    $move->close();

    $mysqli->commit();
    audit_log('bookings', 'assign', 'booking_items', $lineId, null, ['booking_id' => $bookingId, 'item_id' => $itemId, 'quantity' => $quantity]);
    flash('success', 'Booking item allocated.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Item allocation failed: ' . $exception->getMessage());
}

action_redirect_back('modules/bookings/index.php');
