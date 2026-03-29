<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('marketplace.view');
enforce_tenant_lock_for_write();
enforceFeatureForCurrentTenant('has_marketplace', 'subscriptions');
ensure_marketplace_social_tables();

$tenantId = (int) current_tenant_id();
$userId = (int) ((auth_user() ?: [])['id'] ?? 0);
$listingId = (int) post_str('listing_id', '0');

if ($listingId <= 0 || $userId <= 0) {
    flash('error', 'Invalid listing selected.');
    action_redirect_back('modules/marketplace/index.php');
}

$mysqli = db();
$listingStmt = $mysqli->prepare('SELECT c.id, c.tenant_id, c.is_active, IFNULL(mp.is_public, 0) AS profile_public FROM marketplace_catalogue c LEFT JOIN marketplace_profiles mp ON mp.tenant_id = c.tenant_id WHERE c.id = ? LIMIT 1');
$listingStmt->bind_param('i', $listingId);
$listingStmt->execute();
$listing = $listingStmt->get_result()->fetch_assoc();
$listingStmt->close();

if (!$listing || (int) ($listing['is_active'] ?? 0) !== 1) {
    flash('error', 'Listing not found or unavailable.');
    action_redirect_back('modules/marketplace/index.php');
}

$canAccess = ((int) $listing['tenant_id'] === $tenantId) || ((int) $listing['profile_public'] === 1);
if (!$canAccess) {
    flash('error', 'You cannot engage with this listing.');
    action_redirect_back('modules/marketplace/index.php');
}

$check = $mysqli->prepare('SELECT id FROM marketplace_listing_likes WHERE listing_id = ? AND user_id = ? LIMIT 1');
$check->bind_param('ii', $listingId, $userId);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    $del = $mysqli->prepare('DELETE FROM marketplace_listing_likes WHERE listing_id = ? AND user_id = ? LIMIT 1');
    $del->bind_param('ii', $listingId, $userId);
    $del->execute();
    $del->close();
    audit_log('marketplace', 'unlike', 'marketplace_catalogue', $listingId, null, ['user_id' => $userId]);
} else {
    $ins = $mysqli->prepare('INSERT INTO marketplace_listing_likes (listing_id, tenant_id, user_id, created_at) VALUES (?, ?, ?, NOW())');
    $ins->bind_param('iii', $listingId, $tenantId, $userId);
    $ins->execute();
    $ins->close();
    audit_log('marketplace', 'like', 'marketplace_catalogue', $listingId, null, ['user_id' => $userId]);
}

action_redirect_back('modules/marketplace/index.php');
