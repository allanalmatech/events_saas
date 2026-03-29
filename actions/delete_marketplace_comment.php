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
$commentId = (int) post_str('comment_id', '0');

if ($commentId <= 0 || $userId <= 0) {
    flash('error', 'Invalid comment selected.');
    action_redirect_back('modules/marketplace/index.php');
}

$mysqli = db();
$oldStmt = $mysqli->prepare('SELECT id, listing_id, tenant_id, user_id, comment_text, created_at FROM marketplace_listing_comments WHERE id = ? LIMIT 1');
$oldStmt->bind_param('i', $commentId);
$oldStmt->execute();
$old = $oldStmt->get_result()->fetch_assoc();
$oldStmt->close();

if (!$old || (int) $old['user_id'] !== $userId || (int) $old['tenant_id'] !== $tenantId) {
    flash('error', 'Comment not found or not deletable by you.');
    action_redirect_back('modules/marketplace/index.php');
}

$stmt = $mysqli->prepare('DELETE FROM marketplace_listing_comments WHERE id = ? AND user_id = ? AND tenant_id = ? LIMIT 1');
$stmt->bind_param('iii', $commentId, $userId, $tenantId);
$stmt->execute();
$stmt->close();

audit_log('marketplace', 'comment_delete', 'marketplace_listing_comments', $commentId, $old, null);
flash('success', 'Comment deleted.');
action_redirect_back('modules/marketplace/index.php');
