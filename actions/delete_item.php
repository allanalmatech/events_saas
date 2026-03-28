<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('items.delete');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$itemId = (int) post_str('item_id', '0');

if ($itemId <= 0) {
    flash('error', 'Invalid item selected for deletion.');
    action_redirect_back('modules/items/index.php');
}

$mysqli = db();

$itemStmt = $mysqli->prepare('SELECT id, item_name, quantity_hired_out FROM items WHERE id = ? AND tenant_id = ? LIMIT 1');
$itemStmt->bind_param('ii', $itemId, $tenantId);
$itemStmt->execute();
$item = $itemStmt->get_result()->fetch_assoc();
$itemStmt->close();

if (!$item) {
    flash('error', 'Item not found.');
    action_redirect_back('modules/items/index.php');
}

if ((int) $item['quantity_hired_out'] > 0) {
    flash('error', 'Cannot delete item because some quantity is currently hired out.');
    action_redirect_back('modules/items/index.php');
}

$checks = [
    'booking_items' => 'SELECT COUNT(*) c FROM booking_items WHERE tenant_id = ? AND item_id = ?',
    'invoice_items' => 'SELECT COUNT(*) c FROM invoice_items WHERE tenant_id = ? AND line_type = "item" AND reference_id = ?',
    'item_stock_movements' => 'SELECT COUNT(*) c FROM item_stock_movements WHERE tenant_id = ? AND item_id = ? AND movement_type <> "add"',
];

foreach ($checks as $label => $sql) {
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $tenantId, $itemId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $count = (int) ($row['c'] ?? 0);
    if ($count > 0) {
        flash('error', 'Cannot delete item. It is already associated with transactions in ' . $label . '.');
        action_redirect_back('modules/items/index.php');
    }
}

$delete = $mysqli->prepare('DELETE FROM items WHERE id = ? AND tenant_id = ? LIMIT 1');
$delete->bind_param('ii', $itemId, $tenantId);
$delete->execute();
$delete->close();

audit_log('items', 'delete', 'items', $itemId, $item, null);
flash('success', 'Item deleted successfully.');
action_redirect_back('modules/items/index.php');
