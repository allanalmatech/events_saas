<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'SaaS Billing';
$moduleKey = 'billing';
$modulePermission = 'subscriptions.view';
$moduleDescription = 'Manage tenant billing invoices, payments, reminders, and account lock enforcement.';

$isDirector = auth_role() === 'director';
if (!$isDirector) {
    require_tenant_user();
}

$contentRenderer = function () use ($isDirector): void {
    $tenantId = current_tenant_id();
    $invoices = [];
    $payments = [];
    $tenants = [];
    $tenantBillingProfiles = [];
    $tenantAdvanceMap = [];

    if ($mysqli = db_try()) {
        if ($isDirector) {
            $tenantsQ = $mysqli->query('SELECT id, business_name FROM tenants ORDER BY business_name');
            $tenants = $tenantsQ ? $tenantsQ->fetch_all(MYSQLI_ASSOC) : [];

            $profileQ = $mysqli->query('SELECT t.id AS tenant_id, t.business_name, ts.id AS subscription_id, ts.billing_cycle, sp.plan_name, sp.price_monthly, sp.price_quarterly, sp.price_semiannual, sp.price_annual FROM tenants t LEFT JOIN tenant_subscriptions ts ON ts.id = (SELECT x.id FROM tenant_subscriptions x WHERE x.tenant_id = t.id ORDER BY x.id DESC LIMIT 1) LEFT JOIN subscription_plans sp ON sp.id = ts.plan_id ORDER BY t.business_name');
            $profiles = $profileQ ? $profileQ->fetch_all(MYSQLI_ASSOC) : [];
            foreach ($profiles as $profile) {
                $tenantBillingProfiles[(int) $profile['tenant_id']] = $profile;
            }

            $invoicesQ = $mysqli->query('SELECT i.id, i.tenant_id, t.business_name, i.invoice_number, i.amount_charged, i.amount_paid, i.balance, i.payment_status, i.due_date FROM tenant_billing_invoices i INNER JOIN tenants t ON t.id = i.tenant_id ORDER BY i.id DESC LIMIT 120');
            $invoices = $invoicesQ ? $invoicesQ->fetch_all(MYSQLI_ASSOC) : [];

            $paymentsQ = $mysqli->query('SELECT p.id, p.tenant_id, p.billing_invoice_id, t.business_name, i.invoice_number, p.amount, p.payment_method, p.payment_reference, p.payment_date FROM tenant_billing_payments p INNER JOIN tenants t ON t.id = p.tenant_id INNER JOIN tenant_billing_invoices i ON i.id = p.billing_invoice_id ORDER BY p.id DESC LIMIT 120');
            $payments = $paymentsQ ? $paymentsQ->fetch_all(MYSQLI_ASSOC) : [];

            ensure_tenant_billing_advances_table();
            $advQ = $mysqli->query('SELECT tenant_id, amount_available FROM tenant_billing_advances');
            $advRows = $advQ ? $advQ->fetch_all(MYSQLI_ASSOC) : [];
            foreach ($advRows as $adv) {
                $tenantAdvanceMap[(int) $adv['tenant_id']] = (float) $adv['amount_available'];
            }
        } elseif ($tenantId) {
            $stmt = $mysqli->prepare('SELECT id, invoice_number, amount_charged, amount_paid, balance, payment_status, due_date FROM tenant_billing_invoices WHERE tenant_id = ? ORDER BY id DESC LIMIT 40');
            $tid = (int) $tenantId;
            $stmt->bind_param('i', $tid);
            $stmt->execute();
            $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $payStmt = $mysqli->prepare('SELECT id, tenant_id, billing_invoice_id, amount, payment_method, payment_reference, payment_date FROM tenant_billing_payments WHERE tenant_id = ? ORDER BY id DESC LIMIT 80');
            $payStmt->bind_param('i', $tid);
            $payStmt->execute();
            $payments = $payStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $payStmt->close();
        }
    }

    foreach ($invoices as &$invoice) {
        $charged = (float) ($invoice['amount_charged'] ?? 0);
        $paid = (float) ($invoice['amount_paid'] ?? 0);
        $difference = $charged - $paid;
        $invoice['balance'] = $difference;

        if (abs($difference) <= 0.00001) {
            $invoice['status_label'] = 'Paid';
        } elseif ($difference > 0 && $paid > 0) {
            $invoice['status_label'] = 'Partial Paid';
        } elseif ($difference > 0) {
            $invoice['status_label'] = 'Uncleared';
        } else {
            $invoice['status_label'] = 'Overpaid';
        }
    }
    unset($invoice);

    ?>
    <style>
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
        .toolbar-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:760px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .smart-form { display:block; }
        .smart-form .field { margin-bottom:12px; }
        .smart-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .smart-note { font-size:12px; color:var(--muted); margin:-4px 0 10px; }
        .billing-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .billing-tab-btn.active { border-color:var(--primary); background:color-mix(in srgb, var(--primary) 18%, transparent); }
        .billing-panel { display:none; }
        .billing-panel.active { display:block; }
        @media (max-width: 760px) { .smart-grid { grid-template-columns:1fr; } }
    </style>

    <?php if ($isDirector): ?>
        <div class="toolbar" style="margin-bottom:14px;">
            <div class="muted">Issue billing invoices and record tenant payments.</div>
            <div class="toolbar-actions">
                <button class="btn btn-primary" type="button" data-modal-open="billing-invoice-modal">Create Billing Invoice</button>
                <button class="btn btn-ghost" type="button" data-modal-open="billing-payment-modal">Record Billing Payment</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="billing-tabs">
        <button class="btn btn-ghost billing-tab-btn" type="button" data-billing-tab="ledger">Ledger</button>
        <button class="btn btn-ghost billing-tab-btn" type="button" data-billing-tab="payments">Payments</button>
    </div>

    <section class="card billing-panel" data-billing-panel="ledger">
        <h3 style="margin-top:0;">Billing Ledger</h3>
        <table class="table">
            <thead><tr><?php if ($isDirector): ?><th>Tenant</th><?php endif; ?><th>Invoice</th><th>Charged</th><th>Paid</th><th>Balance</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $row): ?>
                <?php
                $charged = (float) ($row['amount_charged'] ?? 0);
                $paid = (float) ($row['amount_paid'] ?? 0);
                $balance = (float) ($row['balance'] ?? 0);
                $statusLabel = (string) ($row['status_label'] ?? 'Uncleared');
                ?>
                <tr>
                    <?php if ($isDirector): ?><td><?php echo e($row['business_name']); ?></td><?php endif; ?>
                    <td><?php echo e($row['invoice_number']); ?></td>
                    <td><?php echo number_format($charged, 2); ?></td>
                    <td><?php echo number_format($paid, 2); ?></td>
                    <td>
                        <?php if ($balance > 0.00001): ?>
                            <?php echo number_format($balance, 2); ?> <span class="muted">(Balance)</span>
                        <?php elseif ($balance < -0.00001): ?>
                            <?php echo number_format(abs($balance), 2); ?> <span class="muted">(Credit)</span>
                        <?php else: ?>
                            0.00
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($row['due_date']); ?></td>
                    <td><?php echo e($statusLabel); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$invoices): ?><tr><td colspan="7" class="muted">No billing records yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card billing-panel" data-billing-panel="payments">
        <h3 style="margin-top:0;">Billing Payments</h3>
        <table class="table">
            <thead><tr><?php if ($isDirector): ?><th>Tenant</th><?php endif; ?><th>Invoice</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th><?php if ($isDirector): ?><th>Edit</th><th>Delete</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
                <tr>
                    <?php if ($isDirector): ?><td><?php echo e($pay['business_name']); ?></td><?php endif; ?>
                    <td><?php echo e($pay['invoice_number'] ?? ('#' . (int) $pay['billing_invoice_id'])); ?></td>
                    <td><?php echo number_format((float) $pay['amount'], 2); ?></td>
                    <td><?php echo e($pay['payment_method']); ?></td>
                    <td><?php echo e($pay['payment_reference']); ?></td>
                    <td><?php echo e($pay['payment_date']); ?></td>
                    <?php if ($isDirector): ?>
                        <td><button class="btn btn-ghost" type="button" data-modal-open="billing-payment-edit-<?php echo (int) $pay['id']; ?>" title="Edit payment"><i class="fa-solid fa-pencil"></i></button></td>
                        <td>
                            <form method="post" action="<?php echo e(app_url('actions/billing_payment_delete.php')); ?>">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="payment_id" value="<?php echo (int) $pay['id']; ?>">
                                <button class="btn btn-ghost" type="submit" data-confirm="Delete this billing payment? This will recalculate invoice totals and tenant advance."><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="<?php echo $isDirector ? '8' : '6'; ?>" class="muted">No billing payments recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <?php if ($isDirector): ?>
        <?php foreach ($payments as $pay): ?>
            <div class="modal-backdrop" id="billing-payment-edit-<?php echo (int) $pay['id']; ?>">
                <div class="card modal-card">
                    <div class="modal-header"><h3 style="margin:0;">Edit Billing Payment</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                    <form method="post" action="<?php echo e(app_url('actions/billing_payment_update.php')); ?>" class="smart-form">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="payment_id" value="<?php echo (int) $pay['id']; ?>">
                        <div class="smart-grid">
                            <div class="field"><label>Invoice</label><input value="<?php echo e($pay['invoice_number'] ?? ('#' . (int) $pay['billing_invoice_id'])); ?>" readonly></div>
                            <div class="field"><label>Amount</label><input name="amount" type="number" step="0.01" min="0.01" value="<?php echo e((string) $pay['amount']); ?>" required></div>
                            <div class="field"><label>Method</label><select name="payment_method"><option value="cash" <?php echo ($pay['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option><option value="mobile_money" <?php echo ($pay['payment_method'] ?? '') === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option><option value="bank_transfer" <?php echo ($pay['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option><option value="card" <?php echo ($pay['payment_method'] ?? '') === 'card' ? 'selected' : ''; ?>>Card</option><option value="cheque" <?php echo ($pay['payment_method'] ?? '') === 'cheque' ? 'selected' : ''; ?>>Cheque</option><option value="other" <?php echo ($pay['payment_method'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option></select></div>
                            <div class="field"><label>Reference</label><input name="payment_reference" value="<?php echo e((string) ($pay['payment_reference'] ?? '')); ?>"></div>
                            <div class="field"><label>Date</label><input name="payment_date" type="date" value="<?php echo e($pay['payment_date']); ?>"></div>
                        </div>
                        <button class="btn btn-primary" type="submit">Update Payment</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($isDirector): ?>
    <div class="modal-backdrop" id="billing-invoice-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Create Billing Invoice</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <p class="smart-note">Create a tenant billing invoice for the current cycle and due date.</p>
            <form method="post" action="<?php echo e(app_url('actions/billing_invoice.php')); ?>" class="smart-form">
                <?php echo csrf_input(); ?>
                <div class="smart-grid">
                    <div class="field"><label>Tenant</label><select name="tenant_id"><?php foreach ($tenants as $tenant): ?><option value="<?php echo (int) $tenant['id']; ?>"><?php echo e($tenant['business_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Subscription</label><input value="Auto-linked to selected tenant's current subscription" readonly></div>
                    <div class="field"><label>Current Plan</label><input id="billing-invoice-plan-name" value="-" readonly></div>
                    <div class="field"><label>Billing Cycle</label><input id="billing-invoice-cycle" value="-" readonly></div>
                    <div class="field"><label>Base Amount (Plan)</label><input id="billing-invoice-amount" value="0.00" readonly></div>
                    <div class="field"><label>Surcharge (optional)</label><input id="billing-invoice-surcharge" name="surcharge_amount" type="number" step="0.01" min="0" value="0"></div>
                    <div class="field"><label>Surcharge Note</label><input name="surcharge_note" placeholder="Reason for surcharge"></div>
                    <div class="field"><label>Total Charged</label><input id="billing-invoice-total" value="0.00" readonly></div>
                    <div class="field"><label>Due Date</label><input name="due_date" type="date" value="<?php echo e(date('Y-m-d', strtotime('+7 days'))); ?>"></div>
                </div>
                <p class="smart-note" id="billing-invoice-note" style="display:none;">Selected tenant has no active subscription plan price for invoicing.</p>
                <button class="btn btn-primary" type="submit">Create Invoice</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="billing-payment-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Record Billing Payment</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <p class="smart-note">Capture payment against a billing invoice and keep lock status accurate.</p>
            <form method="post" action="<?php echo e(app_url('actions/billing_payment.php')); ?>" class="smart-form">
                <?php echo csrf_input(); ?>
                <div class="smart-grid">
                    <div class="field"><label>Tenant</label><select name="tenant_id" id="billing-payment-tenant-select"><?php foreach ($tenants as $tenant): ?><option value="<?php echo (int) $tenant['id']; ?>"><?php echo e($tenant['business_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Billing Invoice</label><select name="billing_invoice_id" id="billing-payment-invoice-select" required></select></div>
                    <div class="field"><label>Amount</label><input name="amount" type="number" step="0.01" min="0.01" required></div>
                    <div class="field"><label>Method</label><select name="payment_method"><option value="cash">Cash</option><option value="mobile_money">Mobile Money</option><option value="bank_transfer">Bank Transfer</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="other">Other</option></select></div>
                    <div class="field"><label>Reference</label><input name="payment_reference"></div>
                    <div class="field"><label>Date</label><input name="payment_date" type="date" value="<?php echo e(date('Y-m-d')); ?>"></div>
                </div>
                <p class="smart-note" id="billing-tenant-advance-note">Advance available: 0.00</p>
                <p class="smart-note" id="billing-payment-note" style="display:none;">No unpaid invoice exists for this tenant. Choose another tenant or create an invoice first.</p>
                <button class="btn btn-primary" type="submit" id="billing-payment-submit">Save Payment</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var billingInvoices = <?php echo json_encode($invoices); ?> || [];
            var tenantBillingProfiles = <?php echo json_encode($tenantBillingProfiles); ?> || {};
            var tenantAdvanceMap = <?php echo json_encode($tenantAdvanceMap); ?> || {};
            var openButtons = document.querySelectorAll('[data-modal-open]');
            var closeButtons = document.querySelectorAll('[data-modal-close]');
            var tenantSelect = document.getElementById('billing-payment-tenant-select');
            var invoiceSelect = document.getElementById('billing-payment-invoice-select');
            var paymentSubmitBtn = document.getElementById('billing-payment-submit');
            var paymentNote = document.getElementById('billing-payment-note');
            var tenantAdvanceNote = document.getElementById('billing-tenant-advance-note');
            var invoiceTenantSelect = document.querySelector('#billing-invoice-modal select[name="tenant_id"]');
            var invoicePlanName = document.getElementById('billing-invoice-plan-name');
            var invoiceCycle = document.getElementById('billing-invoice-cycle');
            var invoiceAmount = document.getElementById('billing-invoice-amount');
            var invoiceSurcharge = document.getElementById('billing-invoice-surcharge');
            var invoiceTotal = document.getElementById('billing-invoice-total');
            var invoiceNote = document.getElementById('billing-invoice-note');
            var invoiceSubmitBtn = document.querySelector('#billing-invoice-modal button[type="submit"]');

            function openModal(id) {
                var modal = document.getElementById(id);
                if (modal) { modal.classList.add('open'); }
            }

            function closeModal(el) {
                var modal = el.closest('.modal-backdrop');
                if (modal) { modal.classList.remove('open'); }
            }

            for (var i = 0; i < openButtons.length; i++) {
                openButtons[i].addEventListener('click', function () { openModal(this.getAttribute('data-modal-open')); });
            }
            for (var j = 0; j < closeButtons.length; j++) {
                closeButtons[j].addEventListener('click', function () { closeModal(this); });
            }

            var backdrops = document.querySelectorAll('.modal-backdrop');
            for (var k = 0; k < backdrops.length; k++) {
                backdrops[k].addEventListener('click', function (event) {
                    if (event.target === this) { this.classList.remove('open'); }
                });
            }

            function refreshInvoiceOptions() {
                if (!tenantSelect || !invoiceSelect) {
                    return;
                }
                var tenantId = String(tenantSelect.value || '');
                invoiceSelect.innerHTML = '';

                if (tenantAdvanceNote) {
                    var adv = parseFloat(tenantAdvanceMap[tenantId] || 0);
                    tenantAdvanceNote.textContent = 'Advance available: ' + adv.toFixed(2);
                }

                var filtered = [];
                for (var i = 0; i < billingInvoices.length; i++) {
                    var row = billingInvoices[i];
                    var sameTenant = String(row.tenant_id) === tenantId;
                    var balance = parseFloat(row.balance || '0');
                    if (sameTenant && balance > 0) {
                        filtered.push(row);
                    }
                }

                if (!filtered.length) {
                    var emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.textContent = 'No unpaid invoice for selected tenant';
                    invoiceSelect.appendChild(emptyOpt);
                    if (paymentSubmitBtn) {
                        paymentSubmitBtn.disabled = true;
                    }
                    if (paymentNote) {
                        paymentNote.style.display = '';
                    }
                    return;
                }

                for (var j = 0; j < filtered.length; j++) {
                    var item = filtered[j];
                    var opt = document.createElement('option');
                    opt.value = String(item.id);
                    var due = String(item.due_date || '');
                    var bal = Number(item.balance || 0).toFixed(2);
                    opt.textContent = String(item.invoice_number) + ' | Bal: ' + bal + ' | Due: ' + due;
                    invoiceSelect.appendChild(opt);
                }

                if (paymentSubmitBtn) {
                    paymentSubmitBtn.disabled = false;
                }
                if (paymentNote) {
                    paymentNote.style.display = 'none';
                }
            }

            if (tenantSelect && invoiceSelect) {
                tenantSelect.addEventListener('change', refreshInvoiceOptions);
                refreshInvoiceOptions();
            }

            function billingPriceForProfile(profile) {
                if (!profile) {
                    return 0;
                }
                var cycle = String(profile.billing_cycle || 'monthly');
                if (cycle === 'quarterly') {
                    return parseFloat(profile.price_quarterly || 0);
                }
                if (cycle === 'semiannual') {
                    return parseFloat(profile.price_semiannual || 0);
                }
                if (cycle === 'annual') {
                    return parseFloat(profile.price_annual || 0);
                }
                return parseFloat(profile.price_monthly || 0);
            }

            function refreshInvoiceComputedFields() {
                if (!invoiceTenantSelect || !invoicePlanName || !invoiceCycle || !invoiceAmount) {
                    return;
                }
                var tenantId = String(invoiceTenantSelect.value || '');
                var profile = tenantBillingProfiles[tenantId] || null;
                var planName = profile && profile.plan_name ? String(profile.plan_name) : '-';
                var cycle = profile && profile.billing_cycle ? String(profile.billing_cycle) : '-';
                var amount = billingPriceForProfile(profile);

                invoicePlanName.value = planName;
                invoiceCycle.value = cycle;
                invoiceAmount.value = amount.toFixed(2);

                var surcharge = invoiceSurcharge ? parseFloat(invoiceSurcharge.value || '0') : 0;
                if (!(surcharge >= 0)) {
                    surcharge = 0;
                }
                if (invoiceTotal) {
                    invoiceTotal.value = (amount + surcharge).toFixed(2);
                }

                var invalid = !profile || !profile.subscription_id || amount <= 0;
                if (invoiceSubmitBtn) {
                    invoiceSubmitBtn.disabled = invalid;
                }
                if (invoiceNote) {
                    invoiceNote.style.display = invalid ? '' : 'none';
                }
            }

            if (invoiceTenantSelect) {
                invoiceTenantSelect.addEventListener('change', refreshInvoiceComputedFields);
                refreshInvoiceComputedFields();
            }

            if (invoiceSurcharge) {
                invoiceSurcharge.addEventListener('input', refreshInvoiceComputedFields);
            }

            var tabButtons = document.querySelectorAll('[data-billing-tab]');
            var tabPanels = document.querySelectorAll('[data-billing-panel]');
            function activateBillingTab(key) {
                for (var t = 0; t < tabButtons.length; t++) {
                    tabButtons[t].classList.toggle('active', tabButtons[t].getAttribute('data-billing-tab') === key);
                }
                for (var p = 0; p < tabPanels.length; p++) {
                    tabPanels[p].classList.toggle('active', tabPanels[p].getAttribute('data-billing-panel') === key);
                }
            }
            for (var b = 0; b < tabButtons.length; b++) {
                tabButtons[b].addEventListener('click', function () {
                    activateBillingTab(this.getAttribute('data-billing-tab'));
                });
            }
            if (tabButtons.length) {
                activateBillingTab('ledger');
            }
        })();
    </script>
    <?php endif; ?>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
