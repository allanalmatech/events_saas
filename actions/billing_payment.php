<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$tenantId = (int) post_str('tenant_id', '0');
$billingInvoiceId = (int) post_str('billing_invoice_id', '0');
$amount = (float) post_str('amount', '0');
$method = post_str('payment_method');
$reference = post_str('payment_reference');
$date = post_str('payment_date', date('Y-m-d'));

if ($tenantId <= 0 || $billingInvoiceId <= 0 || $amount <= 0) {
    flash('error', 'Invalid billing payment payload.');
    action_redirect_back('modules/billing/index.php');
}

$invoiceCheck = db()->prepare('SELECT id, balance FROM tenant_billing_invoices WHERE id = ? AND tenant_id = ? LIMIT 1');
$invoiceCheck->bind_param('ii', $billingInvoiceId, $tenantId);
$invoiceCheck->execute();
$invoiceRow = $invoiceCheck->get_result()->fetch_assoc();
$invoiceCheck->close();

if (!$invoiceRow) {
    flash('error', 'Selected billing invoice does not belong to that tenant.');
    action_redirect_back('modules/billing/index.php');
}

if ((float) ($invoiceRow['balance'] ?? 0) <= 0) {
    flash('error', 'Selected invoice has no outstanding balance.');
    action_redirect_back('modules/billing/index.php');
}

$currentBalance = (float) ($invoiceRow['balance'] ?? 0);
$advanceAmount = max($amount - $currentBalance, 0);

$mysqli = db();
$mysqli->begin_transaction();

try {
    $stmt = $mysqli->prepare('INSERT INTO tenant_billing_payments (tenant_id, billing_invoice_id, amount, payment_method, payment_reference, payment_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('iidsss', $tenantId, $billingInvoiceId, $amount, $method, $reference, $date);
    $stmt->execute();
    $paymentId = (int) $stmt->insert_id;
    $stmt->close();

    recalculate_billing_invoice_totals($tenantId, $billingInvoiceId);
    recalculate_tenant_billing_advance($tenantId);

    $mysqli->commit();
    audit_log('billing', 'payment', 'tenant_billing_payments', $paymentId, null, ['billing_invoice_id' => $billingInvoiceId, 'amount' => $amount, 'advance_amount' => $advanceAmount]);
    if ($advanceAmount > 0.00001) {
        flash('success', 'Billing payment recorded. Excess ' . number_format($advanceAmount, 2) . ' saved as tenant advance.');
    } else {
        flash('success', 'Billing payment recorded.');
    }
} catch (Throwable $exception) {
    $mysqli->rollback();
    flash('error', 'Billing payment failed: ' . $exception->getMessage());
}

action_redirect_back('modules/billing/index.php');
