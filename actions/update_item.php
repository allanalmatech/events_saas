<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('items.edit');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$itemId = (int) post_str('item_id', '0');
$name = post_str('item_name');
$sku = post_str('sku');
$unitType = post_str('unit_type');
$qtyTotal = (int) post_str('quantity_total', '0');
$ownerType = post_str('owner_type', 'owned');
$status = post_str('status', 'active');

if ($itemId <= 0 || $name === '' || $qtyTotal < 0) {
    flash('error', 'Invalid item update payload.');
    action_redirect_back('modules/items/index.php');
}

$mysqli = db();
$oldStmt = $mysqli->prepare('SELECT id, item_name, sku, quantity_total, quantity_in_store, quantity_hired_out, owner_type, status FROM items WHERE id = ? AND tenant_id = ? LIMIT 1');
$oldStmt->bind_param('ii', $itemId, $tenantId);
$oldStmt->execute();
$old = $oldStmt->get_result()->fetch_assoc();
$oldStmt->close();

if (!$old) {
    flash('error', 'Item not found for this tenant.');
    action_redirect_back('modules/items/index.php');
}

if ($sku === '') {
    $sku = (string) $old['sku'];
}

$hiredOut = (int) $old['quantity_hired_out'];
if ($qtyTotal < $hiredOut) {
    flash('error', 'Quantity total cannot be less than already hired-out quantity (' . $hiredOut . ').');
    action_redirect_back('modules/items/index.php?edit_id=' . $itemId);
}

$qtyInStore = $qtyTotal - $hiredOut;

try {
    $stmt = $mysqli->prepare('UPDATE items SET item_name = ?, sku = ?, unit_type = ?, quantity_total = ?, quantity_in_store = ?, owner_type = ?, status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
    $stmt->bind_param('sssiissii', $name, $sku, $unitType, $qtyTotal, $qtyInStore, $ownerType, $status, $itemId, $tenantId);
    $stmt->execute();
    $stmt->close();

    if ((int) $old['quantity_total'] !== $qtyTotal) {
        $delta = $qtyTotal - (int) $old['quantity_total'];
        $movementType = 'adjustment';
        $absDelta = abs($delta);
        if ($absDelta > 0) {
            $mv = $mysqli->prepare('INSERT INTO item_stock_movements (tenant_id, item_id, movement_type, quantity, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $note = 'Manual quantity edit';
            $mv->bind_param('iisis', $tenantId, $itemId, $movementType, $absDelta, $note);
            $mv->execute();
            $mv->close();
        }
    }

    audit_log('items', 'edit', 'items', $itemId, $old, ['item_name' => $name, 'sku' => $sku, 'quantity_total' => $qtyTotal, 'quantity_in_store' => $qtyInStore, 'owner_type' => $ownerType, 'status' => $status]);
    flash('success', 'Item updated successfully.');
} catch (Throwable $exception) {
    if (stripos($exception->getMessage(), 'uq_items_tenant_sku') !== false) {
        flash('error', 'That SKU is already used by another item.');
    } else {
        flash('error', 'Could not update item: ' . $exception->getMessage());
    }
}

action_redirect_back('modules/items/index.php');
