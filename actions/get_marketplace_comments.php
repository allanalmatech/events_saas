<?php
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json');

require_tenant_user();

if (!can('marketplace.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

enforceFeatureForCurrentTenant('has_marketplace', 'subscriptions');
ensure_marketplace_social_tables();

$tenantId = (int) current_tenant_id();
$userId = (int) ((auth_user() ?: [])['id'] ?? 0);
$listingId = (int) get_str('listing_id', '0');

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

if (!$listing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Listing not found.']);
    exit;
}

$ownerTenantId = (int) ($listing['tenant_id'] ?? 0);
$isActive = (int) ($listing['is_active'] ?? 0) === 1;
$canAccess = ($ownerTenantId === $tenantId) || ((int) ($listing['profile_public'] ?? 0) === 1);
if (!$canAccess || !$isActive) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Listing is not visible.']);
    exit;
}

$stmt = $mysqli->prepare('SELECT mc.id, mc.user_id, mc.comment_text, mc.created_at, TIMESTAMPDIFF(SECOND, mc.created_at, NOW()) AS age_seconds, u.full_name FROM marketplace_listing_comments mc INNER JOIN tenant_users u ON u.id = mc.user_id WHERE mc.listing_id = ? ORDER BY mc.created_at DESC LIMIT 40');
$stmt->bind_param('i', $listingId);
$stmt->execute();
$res = $stmt->get_result();

$comments = [];
while ($res && ($row = $res->fetch_assoc())) {
    $commentUserId = (int) ($row['user_id'] ?? 0);
    $isMine = $commentUserId === $userId;
    $comments[] = [
        'id' => (int) ($row['id'] ?? 0),
        'user_name' => (string) ($row['full_name'] ?? ''),
        'comment_text' => (string) ($row['comment_text'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'can_edit' => $isMine && ((int) ($row['age_seconds'] ?? 999999) <= 120),
        'can_delete' => $isMine,
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'comments' => $comments,
]);
