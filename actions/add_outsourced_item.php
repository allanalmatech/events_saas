<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('bookings.edit');

$tenantId = (int) current_tenant_id();
$bookingId = (int) post_str('booking_id', '0');
$itemName = post_str('item_name');
$provider = post_str('provider_name');
$providerPhone = post_str('provider_phone');
$sourceCost = (float) post_str('source_cost', '0');
$quantity = (int) post_str('quantity', '1');

if ($bookingId <= 0 || $itemName === '' || $provider === '') {
    flash('error', 'Booking, item and provider are required for outsourced item entry.');
    action_redirect_back('modules/bookings/index.php');
}

$stmt = db()->prepare('INSERT INTO outsourced_items (tenant_id, booking_id, item_name, provider_name, provider_phone, source_cost, quantity, return_to_owner_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, "pending", NOW())');
$stmt->bind_param('iisssdi', $tenantId, $bookingId, $itemName, $provider, $providerPhone, $sourceCost, $quantity);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('bookings', 'create', 'outsourced_items', $id, null, ['booking_id' => $bookingId, 'item_name' => $itemName]);
flash('success', 'Outsourced item registered.');
action_redirect_back('modules/bookings/index.php');
