<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('payments.create');

$tenantId = (int) current_tenant_id();
$invoiceId = (int) post_str('invoice_id', '0');
$amount = (float) post_str('amount', '0');
$method = post_str('payment_method');
$reference = post_str('payment_reference');
$paymentDate = post_str('payment_date', date('Y-m-d'));

if ($invoiceId <= 0 || $amount <= 0) {
    flash('error', 'Invoice and valid amount are required.');
    action_redirect_back('modules/payments/index.php');
}

$mysqli = db();
$createdBy = (int) auth_user()['id'];
$stmt = $mysqli->prepare('INSERT INTO payments (tenant_id, invoice_id, amount, payment_date, payment_method, payment_reference, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('iidsssi', $tenantId, $invoiceId, $amount, $paymentDate, $method, $reference, $createdBy);
$stmt->execute();
$paymentId = (int) $stmt->insert_id;
$stmt->close();

$up = $mysqli->prepare('UPDATE invoices SET amount_paid = amount_paid + ?, balance_amount = GREATEST(total_amount - (amount_paid + ?), 0), updated_at = NOW() WHERE id = ? AND tenant_id = ?');
$up->bind_param('ddii', $amount, $amount, $invoiceId, $tenantId);
$up->execute();
$up->close();

audit_log('payments', 'create', 'payments', $paymentId, null, ['invoice_id' => $invoiceId, 'amount' => $amount]);
flash('success', 'Payment captured.');
action_redirect_back('modules/payments/index.php');
