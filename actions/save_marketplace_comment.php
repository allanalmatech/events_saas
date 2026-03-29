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
$comment = trim(post_str('comment_text'));

if ($listingId <= 0 || $userId <= 0 || $comment === '') {
    flash('error', 'Comment text is required.');
    action_redirect_back('modules/marketplace/index.php');
}

if (strlen($comment) > 1000) {
    flash('error', 'Comment is too long. Keep it under 1000 characters.');
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
    flash('error', 'You cannot comment on this listing.');
    action_redirect_back('modules/marketplace/index.php');
}

$stmt = $mysqli->prepare('INSERT INTO marketplace_listing_comments (listing_id, tenant_id, user_id, comment_text, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
$stmt->bind_param('iiis', $listingId, $tenantId, $userId, $comment);
$stmt->execute();
$commentId = (int) $stmt->insert_id;
$stmt->close();

audit_log('marketplace', 'comment_create', 'marketplace_listing_comments', $commentId, null, ['listing_id' => $listingId]);
flash('success', 'Comment posted.');
action_redirect_back('modules/marketplace/index.php');
