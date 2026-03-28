<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$tenantId = (int) post_str('tenant_id', '0');
$dueDate = post_str('due_date', date('Y-m-d', strtotime('+7 days')));
$surchargeAmount = (float) post_str('surcharge_amount', '0');
$surchargeNote = post_str('surcharge_note');

if ($tenantId <= 0) {
    flash('error', 'Tenant is required.');
    action_redirect_back('modules/billing/index.php');
}

if ($surchargeAmount < 0) {
    $surchargeAmount = 0;
}

$invoiceNumber = 'SBI-' . date('Ymd') . '-' . strtoupper(substr(md5((string) microtime(true)), 0, 6));
$start = date('Y-m-d');

$mysqli = db();
$subscriptionId = 0;
$cycle = 'monthly';
$amount = 0.0;
$subStmt = $mysqli->prepare('SELECT id FROM tenant_subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1');
if ($subStmt) {
    $subStmt->bind_param('i', $tenantId);
    $subStmt->execute();
    $sub = $subStmt->get_result()->fetch_assoc();
    $subStmt->close();
    if ($sub) {
        $subscriptionId = (int) $sub['id'];
    }
}

if ($subscriptionId <= 0) {
    flash('error', 'Selected tenant has no active subscription record.');
    action_redirect_back('modules/billing/index.php');
}

$planStmt = $mysqli->prepare('SELECT ts.billing_cycle, sp.price_monthly, sp.price_quarterly, sp.price_semiannual, sp.price_annual FROM tenant_subscriptions ts INNER JOIN subscription_plans sp ON sp.id = ts.plan_id WHERE ts.id = ? LIMIT 1');
if ($planStmt) {
    $planStmt->bind_param('i', $subscriptionId);
    $planStmt->execute();
    $plan = $planStmt->get_result()->fetch_assoc();
    $planStmt->close();
    if ($plan) {
        $cycle = (string) ($plan['billing_cycle'] ?? 'monthly');
        if ($cycle === 'quarterly') {
            $amount = (float) ($plan['price_quarterly'] ?? 0);
        } elseif ($cycle === 'semiannual') {
            $amount = (float) ($plan['price_semiannual'] ?? 0);
        } elseif ($cycle === 'annual') {
            $amount = (float) ($plan['price_annual'] ?? 0);
        } else {
            $cycle = 'monthly';
            $amount = (float) ($plan['price_monthly'] ?? 0);
        }
    }
}

if ($amount <= 0) {
    flash('error', 'Could not derive invoice amount from the tenant\'s plan and billing cycle.');
    action_redirect_back('modules/billing/index.php');
}

$amount += $surchargeAmount;

$notes = '';
if ($surchargeAmount > 0) {
    $notes = 'Surcharge: ' . number_format($surchargeAmount, 2);
    if ($surchargeNote !== '') {
        $notes .= ' (' . $surchargeNote . ')';
    }
}

$end = $start;
if ($cycle === 'quarterly') {
    $end = date('Y-m-d', strtotime('+3 months'));
} elseif ($cycle === 'semiannual') {
    $end = date('Y-m-d', strtotime('+6 months'));
} elseif ($cycle === 'annual') {
    $end = date('Y-m-d', strtotime('+12 months'));
} else {
    $end = date('Y-m-d', strtotime('+1 month'));
}

$stmt = $mysqli->prepare('INSERT INTO tenant_billing_invoices (tenant_id, subscription_id, invoice_number, billing_period_start, billing_period_end, billing_cycle, amount_charged, amount_paid, balance, due_date, payment_status, notes, created_at) VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, 0, ?, ?, "unpaid", ?, NOW())');
$balance = $amount;
$stmt->bind_param('iissssddss', $tenantId, $subscriptionId, $invoiceNumber, $start, $end, $cycle, $amount, $balance, $dueDate, $notes);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('billing', 'create', 'tenant_billing_invoices', $id, null, ['invoice_number' => $invoiceNumber, 'amount' => $amount]);
flash('success', 'SaaS billing invoice created.');
action_redirect_back('modules/billing/index.php');
