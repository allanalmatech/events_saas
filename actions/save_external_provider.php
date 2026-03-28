<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('items.create');

$tenantId = (int) current_tenant_id();
$providerName = post_str('provider_name');
$contactPerson = post_str('contact_person');
$phone = post_str('phone');
$email = post_str('email');
$address = post_str('address');
$notes = post_str('notes');

if ($providerName === '') {
    flash('error', 'Provider name is required.');
    action_redirect_back('modules/items/index.php');
}

$stmt = db()->prepare('INSERT INTO external_providers (tenant_id, provider_name, contact_person, phone, email, address, notes, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, "active", NOW(), NOW())');
$stmt->bind_param('issssss', $tenantId, $providerName, $contactPerson, $phone, $email, $address, $notes);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('items', 'create', 'external_providers', $id, null, ['provider_name' => $providerName]);
flash('success', 'External provider saved.');
action_redirect_back('modules/items/index.php');
