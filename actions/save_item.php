<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('items.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$countRes = db()->query('SELECT COUNT(*) c FROM items WHERE tenant_id = ' . $tenantId);
$currentCount = (int) (($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
enforceLimitForCurrentTenant('max_items', $currentCount, 'items');

$name = post_str('item_name');
$sku = post_str('sku');
$unitType = post_str('unit_type');
$qty = (int) post_str('quantity_total', '0');
$ownerType = post_str('owner_type', 'owned');

if ($name === '' || $qty < 0) {
    flash('error', 'Item name is required and quantity cannot be negative.');
    action_redirect_back('modules/items/index.php');
}

$mysqli = db();

if ($sku === '') {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
    if ($prefix === '') {
        $prefix = 'ITM';
    }

    do {
        $candidate = $prefix . '-' . date('ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $check = $mysqli->prepare('SELECT id FROM items WHERE tenant_id = ? AND sku = ? LIMIT 1');
        $check->bind_param('is', $tenantId, $candidate);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();
    } while ($exists);

    $sku = $candidate;
}

$stmt = $mysqli->prepare('INSERT INTO items (tenant_id, item_name, sku, unit_type, quantity_total, quantity_in_store, owner_type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, "active", NOW(), NOW())');
$inStore = $qty;
$stmt->bind_param('isssiis', $tenantId, $name, $sku, $unitType, $qty, $inStore, $ownerType);

try {
    $stmt->execute();
    $itemId = (int) $stmt->insert_id;
    $stmt->close();

    $movement = db()->prepare('INSERT INTO item_stock_movements (tenant_id, item_id, movement_type, quantity, notes, created_at) VALUES (?, ?, "add", ?, "Initial stock", NOW())');
    $movement->bind_param('iii', $tenantId, $itemId, $qty);
    $movement->execute();
    $movement->close();

    audit_log('items', 'create', 'items', $itemId, null, ['item_name' => $name, 'quantity_total' => $qty, 'sku' => $sku]);
    flash('success', 'Item saved with SKU: ' . $sku);
} catch (Throwable $exception) {
    if ($stmt) {
        $stmt->close();
    }
    if (stripos($exception->getMessage(), 'uq_items_tenant_sku') !== false) {
        flash('error', 'That SKU already exists for this tenant. Use another SKU or leave it blank for auto-generation.');
    } else {
        flash('error', 'Could not save item: ' . $exception->getMessage());
    }
}

action_redirect_back('modules/items/index.php');
