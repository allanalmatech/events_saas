<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$paymentId = (int) post_str('payment_id', '0');
if ($paymentId <= 0) {
    flash('error', 'Invalid payment selected for deletion.');
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
    $del = $mysqli->prepare('DELETE FROM tenant_billing_payments WHERE id = ? LIMIT 1');
    $del->bind_param('i', $paymentId);
    $del->execute();
    $del->close();

    recalculate_billing_invoice_totals($tenantId, $billingInvoiceId);
    recalculate_tenant_billing_advance($tenantId);

    $mysqli->commit();
    audit_log('billing', 'payment_delete', 'tenant_billing_payments', $paymentId, $payment, null);
    flash('success', 'Billing payment deleted.');
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Billing payment deletion failed: ' . $exception->getMessage());
}

action_redirect_back('modules/billing/index.php');
