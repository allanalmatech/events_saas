<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json');

action_require_post();
require_tenant_user();

if (!can('marketplace.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

enforce_tenant_lock_for_write();
enforceFeatureForCurrentTenant('has_marketplace', 'subscriptions');
ensure_marketplace_social_tables();

$tenantId = (int) current_tenant_id();
$userId = (int) ((auth_user() ?: [])['id'] ?? 0);
$listingId = (int) post_str('listing_id', '0');

if ($listingId <= 0 || $userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid listing.']);
    exit;
}

$mysqli = db();
$listingStmt = $mysqli->prepare('SELECT c.id, c.tenant_id, c.is_active, IFNULL(mp.is_public, 0) AS profile_public FROM marketplace_catalogue c LEFT JOIN marketplace_profiles mp ON mp.tenant_id = c.tenant_id WHERE c.id = ? LIMIT 1');
$listingStmt->bind_param('i', $listingId);
$listingStmt->execute();
$listing = $listingStmt->get_result()->fetch_assoc();
$listingStmt->close();

if (!$listing || (int) ($listing['is_active'] ?? 0) !== 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Listing unavailable.']);
    exit;
}

$ownerTenantId = (int) ($listing['tenant_id'] ?? 0);
$canAccess = ($ownerTenantId === $tenantId) || ((int) ($listing['profile_public'] ?? 0) === 1);
if (!$canAccess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Listing is not visible.']);
    exit;
}

if ($ownerTenantId === $tenantId) {
    echo json_encode(['success' => true, 'recorded' => false]);
    exit;
}

$stmt = $mysqli->prepare('INSERT INTO marketplace_listing_views (listing_id, viewer_tenant_id, viewer_user_id, first_viewed_at, last_viewed_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_viewed_at = NOW()');
$stmt->bind_param('iii', $listingId, $tenantId, $userId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'recorded' => true]);
