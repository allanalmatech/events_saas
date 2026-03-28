<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$paymentId = (int) post_str('payment_id', '0');
$amount = (float) post_str('amount', '0');
$method = post_str('payment_method');
$reference = post_str('payment_reference');
$date = post_str('payment_date', date('Y-m-d'));

if ($paymentId <= 0 || $amount <= 0) {
    flash('error', 'Invalid payment update payload.');
    action_redirect_back('modules/billing/index.php');
}

$mysqli = db();
$lookup = $mysqli->prepare('SELECT tenant_id, billing_invoice_id, amount FROM tenant_billing_payments WHERE id = ? LIMIT 1');
$lookup->bind_param('i', $paymentId);
$lookup->execute();
$payment = $lookup->get_result()->fetch_assoc();
$lookup->close();

if (!$payment) {
    flash('error', 'Payment record not found.');
    action_redirect_back('modules/billing/index.php');
}

$tenantId = (int) $payment['tenant_id'];
$billingInvoiceId = (int) $payment['billing_invoice_id'];

$mysqli->begin_transaction();

try {
    $stmt = $mysqli->prepare('UPDATE tenant_billing_payments SET amount = ?, payment_method = ?, payment_reference = ?, payment_date = ? WHERE id = ? LIMIT 1');
    $stmt->bind_param('dsssi', $amount, $method, $reference, $date, $paymentId);
    $stmt->execute();
    $stmt->close();

    recalculate_billing_invoice_totals($tenantId, $billingInvoiceId);
    recalculate_tenant_billing_advance($tenantId);

    $mysqli->commit();
    audit_log('billing', 'payment_edit', 'tenant_billing_payments', $paymentId, null, ['amount' => $amount, 'billing_invoice_id' => $billingInvoiceId]);
    flash('success', 'Billing payment updated.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Billing payment update failed: ' . $exception->getMessage());
}

action_redirect_back('modules/billing/index.php');
