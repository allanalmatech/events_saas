<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('receipts.create');

$tenantId = (int) current_tenant_id();
$invoiceId = (int) post_str('invoice_id', '0');
$amount = (float) post_str('amount_paid', '0');
$method = post_str('payment_method');
$reference = post_str('payment_reference');

$allowedMethods = ['cash', 'mobile_money', 'bank_transfer', 'card', 'cheque', 'other'];
if (!in_array($method, $allowedMethods, true)) {
    $method = 'other';
}

if ($invoiceId <= 0 || $amount <= 0) {
    flash('error', 'Invoice and amount are required.');
    action_redirect_back('modules/receipts/index.php');
}

$receiptNo = 'RCT-' . date('Ymd') . '-' . strtoupper(substr(md5((string) microtime(true)), 0, 6));
$receiptDate = date('Y-m-d');
$mysqli = db();

$stmt = $mysqli->prepare('SELECT balance_amount FROM invoices WHERE id = ? AND tenant_id = ? LIMIT 1');
$stmt->bind_param('ii', $invoiceId, $tenantId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$balanceAfter = $row ? max(0, (float) $row['balance_amount'] - $amount) : 0;
$receivedBy = (int) auth_user()['id'];

$stmt = $mysqli->prepare('INSERT INTO receipts (tenant_id, invoice_id, receipt_no, receipt_date, amount_paid, payment_method, payment_reference, balance_after, received_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('iissdssdi', $tenantId, $invoiceId, $receiptNo, $receiptDate, $amount, $method, $reference, $balanceAfter, $receivedBy);
$stmt->execute();
$receiptId = (int) $stmt->insert_id;
$stmt->close();

audit_log('receipts', 'create', 'receipts', $receiptId, null, ['invoice_id' => $invoiceId, 'amount_paid' => $amount]);
flash('success', 'Receipt generated.');
action_redirect_back('modules/receipts/index.php');
