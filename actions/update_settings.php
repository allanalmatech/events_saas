<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('settings.edit');

if (empty((auth_user() ?: [])['is_super_admin'])) {
    flash('error', 'Only Super Users can update business document settings.');
    action_redirect_back('modules/settings/index.php');
}

$tenantId = (int) current_tenant_id();
$invoiceFooter = post_str('invoice_footer');
$receiptFooter = post_str('receipt_footer');
$tax = (float) post_str('default_tax_percent', '0');

$mysqli = db();
$stmt = $mysqli->prepare('UPDATE tenant_settings SET invoice_footer = ?, receipt_footer = ?, default_tax_percent = ?, updated_at = NOW() WHERE tenant_id = ?');
$stmt->bind_param('ssdi', $invoiceFooter, $receiptFooter, $tax, $tenantId);
$stmt->execute();
$stmt->close();

audit_log('settings', 'edit', 'tenant_settings', $tenantId, null, ['default_tax_percent' => $tax]);
flash('success', 'Settings updated.');
action_redirect_back('modules/settings/index.php');
