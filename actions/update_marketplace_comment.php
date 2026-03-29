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
$commentText = trim(post_str('comment_text'));

if ($commentId <= 0 || $commentText === '' || $userId <= 0) {
    flash('error', 'Invalid comment update payload.');
    action_redirect_back('modules/marketplace/index.php');
}

if (strlen($commentText) > 1000) {
    flash('error', 'Comment is too long. Keep it under 1000 characters.');
    action_redirect_back('modules/marketplace/index.php');
}

$mysqli = db();
$oldStmt = $mysqli->prepare('SELECT id, listing_id, tenant_id, user_id, comment_text, created_at FROM marketplace_listing_comments WHERE id = ? LIMIT 1');
$oldStmt->bind_param('i', $commentId);
$oldStmt->execute();
$old = $oldStmt->get_result()->fetch_assoc();
$oldStmt->close();

if (!$old || (int) $old['user_id'] !== $userId || (int) $old['tenant_id'] !== $tenantId) {
    flash('error', 'Comment not found or not editable by you.');
    action_redirect_back('modules/marketplace/index.php');
}

$createdAt = strtotime((string) $old['created_at']);
$ageSeconds = $createdAt ? (time() - $createdAt) : 999999;
if ($ageSeconds > 120) {
    flash('error', 'Comments can only be edited within 2 minutes of posting.');
    action_redirect_back('modules/marketplace/index.php');
}

$stmt = $mysqli->prepare('UPDATE marketplace_listing_comments SET comment_text = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND tenant_id = ?');
$stmt->bind_param('siii', $commentText, $commentId, $userId, $tenantId);
$stmt->execute();
$stmt->close();

audit_log('marketplace', 'comment_edit', 'marketplace_listing_comments', $commentId, $old, ['comment_text' => $commentText]);
flash('success', 'Comment updated.');
action_redirect_back('modules/marketplace/index.php');
