<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('marketplace.create');
enforce_tenant_lock_for_write();
enforceFeatureForCurrentTenant('has_marketplace', 'subscriptions');

$tenantId = (int) current_tenant_id();
$publicName = post_str('public_name');
$about = post_str('about_text');
$email = post_str('contact_email');
$phone = post_str('contact_phone');
$location = post_str('location_text');
$isPublic = (int) post_str('is_public', '1');
$isPublic = $isPublic === 1 ? 1 : 0;

if ($publicName === '') {
    flash('error', 'Public name is required for marketplace profile.');
    action_redirect_back('modules/marketplace/index.php');
}

$mysqli = db();
$stmt = $mysqli->prepare('INSERT INTO marketplace_profiles (tenant_id, public_name, about_text, contact_email, contact_phone, location_text, is_public, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE public_name = VALUES(public_name), about_text = VALUES(about_text), contact_email = VALUES(contact_email), contact_phone = VALUES(contact_phone), location_text = VALUES(location_text), is_public = VALUES(is_public), updated_at = NOW()');
$stmt->bind_param('isssssi', $tenantId, $publicName, $about, $email, $phone, $location, $isPublic);
$stmt->execute();
$stmt->close();

audit_log('marketplace', 'edit', 'marketplace_profiles', $tenantId, null, ['public_name' => $publicName]);
flash('success', 'Marketplace profile updated.');
action_redirect_back('modules/marketplace/index.php');
