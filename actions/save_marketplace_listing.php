<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('marketplace.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
enforceFeatureForCurrentTenant('has_marketplace', 'subscriptions');
$countRes = db()->query('SELECT COUNT(*) c FROM marketplace_catalogue WHERE tenant_id = ' . $tenantId . ' AND is_active = 1');
$currentCount = (int) (($countRes ? $countRes->fetch_assoc()['c'] : 0) ?? 0);
enforceLimitForCurrentTenant('max_marketplace_ads', $currentCount, 'marketplace');

$title = post_str('title');
$listingType = post_str('listing_type', 'service');
$description = post_str('description');
$availability = post_str('availability_status', 'Available');

if (!in_array($listingType, ['service', 'item'], true)) {
    $listingType = 'service';
}

if ($title === '') {
    flash('error', 'Listing title is required.');
    action_redirect_back('modules/marketplace/index.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO marketplace_catalogue (tenant_id, title, listing_type, description, availability_status, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())');
$stmt->bind_param('issss', $tenantId, $title, $listingType, $description, $availability);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('marketplace', 'create', 'marketplace_catalogue', $id, null, ['title' => $title]);
flash('success', 'Marketplace listing published.');
action_redirect_back('modules/marketplace/index.php');
