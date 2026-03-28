<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Subscriptions';
$moduleKey = 'subscriptions';
$modulePermission = 'subscriptions.view';
$moduleDescription = 'Track active plan, billing cycle, usage limits, due dates, and lock status.';

$isDirector = auth_role() === 'director';
if (!$isDirector) {
    require_tenant_user();
}

$contentRenderer = function () use ($isDirector): void {
    $rows = [];
    $plans = [];
    $tenants = [];
    $tenantId = current_tenant_id();

    if ($mysqli = db_try()) {
        $plansQ = $mysqli->query('SELECT id, plan_name FROM subscription_plans WHERE status = "active" ORDER BY id');
        $plans = $plansQ ? $plansQ->fetch_all(MYSQLI_ASSOC) : [];

        if ($isDirector) {
            $tenantsQ = $mysqli->query('SELECT id, business_name FROM tenants ORDER BY business_name');
            $tenants = $tenantsQ ? $tenantsQ->fetch_all(MYSQLI_ASSOC) : [];
            $rowsQ = $mysqli->query('SELECT ts.id AS subscription_id, ts.tenant_id, t.business_name, t.email AS tenant_email, t.phone AS tenant_phone, t.timezone AS tenant_timezone, t.address AS tenant_address, t.account_status AS tenant_account_status, sp.plan_name, ts.billing_cycle, ts.started_at, ts.expires_at, ts.subscription_status FROM tenant_subscriptions ts INNER JOIN tenants t ON t.id = ts.tenant_id INNER JOIN subscription_plans sp ON sp.id = ts.plan_id ORDER BY ts.id DESC LIMIT 50');
            $rows = $rowsQ ? $rowsQ->fetch_all(MYSQLI_ASSOC) : [];
        } elseif ($tenantId) {
            $stmt = $mysqli->prepare('SELECT ts.id AS subscription_id, sp.plan_name, ts.billing_cycle, ts.started_at, ts.expires_at, ts.outstanding_balance, ts.subscription_status FROM tenant_subscriptions ts INNER JOIN subscription_plans sp ON sp.id = ts.plan_id WHERE ts.tenant_id = ? ORDER BY ts.id DESC LIMIT 15');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
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
        .smart-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .smart-note { font-size:12px; color:var(--muted); margin:-6px 0 10px; }
        .tenant-sub-row { cursor:pointer; }
        .tenant-sub-row:hover { background:var(--surface-soft); }
        .btn[disabled] { opacity:0.45; cursor:not-allowed; }
        .summary-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .summary-item { padding:8px 10px; border:1px solid var(--outline); border-radius:8px; background:var(--surface-soft); }
        .summary-item b { display:block; font-size:11px; color:var(--muted); margin-bottom:2px; text-transform:uppercase; letter-spacing:0.3px; }
        @media (max-width: 760px) { .smart-form-grid { grid-template-columns:1fr; } }
    </style>

    <?php if ($isDirector): ?>
        <div class="toolbar" style="margin-bottom:14px;">
            <div class="muted">Manage plan assignment and lifecycle states.</div>
            <div class="toolbar-actions">
                <button class="btn btn-primary" id="open-plan-update" type="button" disabled>Update Tenant Plan</button>
                <button class="btn btn-ghost" type="button" data-modal-open="subscription-status-modal">Transition Status</button>
            </div>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Subscription Records</h3>
        </div>
        <table class="table">
            <thead>
            <?php if ($isDirector): ?>
                <tr><th class="select-col">Pick</th><th>ID</th><th>Tenant</th><th>Plan</th><th>Cycle</th><th>Start</th><th>Expiry</th><th>Status</th></tr>
            <?php else: ?>
                <tr><th>ID</th><th>Plan</th><th>Cycle</th><th>Start</th><th>Expiry</th><th>Outstanding</th><th>Status</th></tr>
            <?php endif; ?>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr<?php echo $isDirector ? ' class="tenant-sub-row" data-tenant-id="' . (int) $row['tenant_id'] . '" data-subscription-id="' . (int) $row['subscription_id'] . '" data-tenant-name="' . e($row['business_name']) . '" data-tenant-email="' . e((string) ($row['tenant_email'] ?? '')) . '" data-tenant-phone="' . e((string) ($row['tenant_phone'] ?? '')) . '" data-tenant-timezone="' . e((string) ($row['tenant_timezone'] ?? '')) . '" data-tenant-address="' . e((string) ($row['tenant_address'] ?? '')) . '" data-tenant-account-status="' . e((string) ($row['tenant_account_status'] ?? '')) . '" data-plan-name="' . e($row['plan_name']) . '" data-billing-cycle="' . e($row['billing_cycle']) . '" data-started-at="' . e($row['started_at']) . '" data-expires-at="' . e($row['expires_at']) . '" data-subscription-status="' . e($row['subscription_status']) . '" title="Click for tenant summary"' : ''; ?>>
                    <?php if ($isDirector): ?>
                        <td class="select-col"><input type="radio" name="pick_subscription" class="tenant-picker" value="<?php echo (int) $row['tenant_id']; ?>"></td>
                    <?php endif; ?>
                    <td><?php echo (int) $row['subscription_id']; ?></td>
                    <?php if ($isDirector): ?>
                        <td><?php echo e($row['business_name']); ?></td>
                    <?php endif; ?>
                    <td><?php echo e($row['plan_name']); ?></td>
                    <td><?php echo e($row['billing_cycle']); ?></td>
                    <td><?php echo e($row['started_at']); ?></td>
                    <td><?php echo e($row['expires_at']); ?></td>
                    <?php if (!$isDirector): ?><td><?php echo number_format((float) ($row['outstanding_balance'] ?? 0), 2); ?></td><?php endif; ?>
                    <td><?php echo e($row['subscription_status']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="muted">No subscription records available.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <?php if ($isDirector): ?>
    <div class="modal-backdrop" id="subscription-plan-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Update Tenant Plan</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <p class="smart-note">Assign a new plan, billing cycle, and apply immediately or at next renewal.</p>
            <form method="post" action="<?php echo e(app_url('actions/subscription_update.php')); ?>" class="smart-form">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="tenant_id" id="subscription-plan-tenant-id" value="">
                <div class="field"><label>Tenant</label><input id="subscription-plan-tenant-name" value="No tenant selected" readonly></div>
                <div class="smart-form-grid">
                    <div class="field"><label>Plan</label><select name="plan_id"><?php foreach ($plans as $plan): ?><option value="<?php echo (int) $plan['id']; ?>"><?php echo e($plan['plan_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Billing Cycle</label><select name="billing_cycle"><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="semiannual">Semiannual</option><option value="annual">Annual</option></select></div>
                    <div class="field"><label>Change Mode</label><select name="change_mode"><option value="immediate">Immediate</option><option value="next_cycle">Next Cycle</option></select></div>
                </div>
                <button class="btn btn-primary" type="submit">Apply Plan Update</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="subscription-status-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Transition Subscription Status</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <p class="smart-note">Use this for operational transitions like overdue, suspended, locked, or cancelled.</p>
            <form method="post" action="<?php echo e(app_url('actions/set_subscription_status.php')); ?>" class="smart-form">
                <?php echo csrf_input(); ?>
                <div class="smart-form-grid">
                    <div class="field"><label>Tenant</label><select name="tenant_id" id="subscription-status-tenant-select"><?php foreach ($tenants as $tenant): ?><option value="<?php echo (int) $tenant['id']; ?>"><?php echo e($tenant['business_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>New Status</label><select name="new_status"><option value="active">active</option><option value="pending_payment">pending_payment</option><option value="overdue">overdue</option><option value="suspended">suspended</option><option value="locked">locked</option><option value="cancelled">cancelled</option><option value="expired">expired</option></select></div>
                </div>
                <button class="btn btn-primary" type="submit">Apply Transition</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="subscription-summary-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Tenant Subscription Summary</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <div class="summary-grid" id="subscription-summary-grid">
                <div class="summary-item"><b>Tenant</b><span data-summary="tenant_name">-</span></div>
                <div class="summary-item"><b>Tenant ID</b><span data-summary="tenant_id">-</span></div>
                <div class="summary-item"><b>Email</b><span data-summary="tenant_email">-</span></div>
                <div class="summary-item"><b>Phone</b><span data-summary="tenant_phone">-</span></div>
                <div class="summary-item"><b>Timezone</b><span data-summary="tenant_timezone">-</span></div>
                <div class="summary-item"><b>Account Status</b><span data-summary="tenant_account_status">-</span></div>
                <div class="summary-item"><b>Subscription ID</b><span data-summary="subscription_id">-</span></div>
                <div class="summary-item"><b>Current Plan</b><span data-summary="plan_name">-</span></div>
                <div class="summary-item"><b>Billing Cycle</b><span data-summary="billing_cycle">-</span></div>
                <div class="summary-item"><b>Subscription Status</b><span data-summary="subscription_status">-</span></div>
                <div class="summary-item"><b>Start</b><span data-summary="started_at">-</span></div>
                <div class="summary-item"><b>Expiry</b><span data-summary="expires_at">-</span></div>
                <div class="summary-item" style="grid-column:1 / -1;"><b>Address</b><span data-summary="tenant_address">-</span></div>
            </div>
            <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <button class="btn btn-primary" type="button" id="summary-edit-plan-btn">Edit Plan for Tenant</button>
                <button class="btn btn-ghost" type="button" data-modal-open="subscription-status-modal" id="summary-status-btn">Transition Status</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var openButtons = document.querySelectorAll('[data-modal-open]');
            var closeButtons = document.querySelectorAll('[data-modal-close]');
            var tenantRows = document.querySelectorAll('.tenant-sub-row[data-tenant-id]');
            var tenantRadios = document.querySelectorAll('.tenant-picker');
            var planTenantInput = document.getElementById('subscription-plan-tenant-id');
            var planTenantNameInput = document.getElementById('subscription-plan-tenant-name');
            var statusTenantSelect = document.getElementById('subscription-status-tenant-select');
            var openPlanUpdateBtn = document.getElementById('open-plan-update');
            var summaryGrid = document.getElementById('subscription-summary-grid');
            var summaryEditPlanBtn = document.getElementById('summary-edit-plan-btn');
            var summaryStatusBtn = document.getElementById('summary-status-btn');
            var selectedTenantId = '';
            var selectedTenantName = '';

            function openModal(id) {
                var modal = document.getElementById(id);
                if (modal) { modal.classList.add('open'); }
            }

            function closeModal(el) {
                var modal = el.closest('.modal-backdrop');
                if (modal) { modal.classList.remove('open'); }
            }

            function closeModalById(id) {
                var modal = document.getElementById(id);
                if (modal) { modal.classList.remove('open'); }
            }

            function setSummaryField(key, value) {
                if (!summaryGrid) {
                    return;
                }
                var el = summaryGrid.querySelector('[data-summary="' + key + '"]');
                if (el) {
                    el.textContent = value && value !== '' ? value : '-';
                }
            }

            function syncTenantSelection(tenantId, tenantName) {
                selectedTenantId = tenantId || '';
                selectedTenantName = tenantName || selectedTenantName || '';
                if (openPlanUpdateBtn) {
                    openPlanUpdateBtn.disabled = selectedTenantId === '';
                }
                if (planTenantInput) {
                    planTenantInput.value = selectedTenantId;
                }
                if (planTenantNameInput) {
                    planTenantNameInput.value = selectedTenantId !== '' ? selectedTenantName : 'No tenant selected';
                }
                if (statusTenantSelect && selectedTenantId !== '') {
                    statusTenantSelect.value = selectedTenantId;
                }
            }

            function fillSummaryFromRow(row) {
                setSummaryField('tenant_name', row.getAttribute('data-tenant-name') || '');
                setSummaryField('tenant_id', row.getAttribute('data-tenant-id') || '');
                setSummaryField('tenant_email', row.getAttribute('data-tenant-email') || '');
                setSummaryField('tenant_phone', row.getAttribute('data-tenant-phone') || '');
                setSummaryField('tenant_timezone', row.getAttribute('data-tenant-timezone') || '');
                setSummaryField('tenant_address', row.getAttribute('data-tenant-address') || '');
                setSummaryField('tenant_account_status', row.getAttribute('data-tenant-account-status') || '');
                setSummaryField('subscription_id', row.getAttribute('data-subscription-id') || '');
                setSummaryField('plan_name', row.getAttribute('data-plan-name') || '');
                setSummaryField('billing_cycle', row.getAttribute('data-billing-cycle') || '');
                setSummaryField('started_at', row.getAttribute('data-started-at') || '');
                setSummaryField('expires_at', row.getAttribute('data-expires-at') || '');
                setSummaryField('subscription_status', row.getAttribute('data-subscription-status') || '');
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

            for (var m = 0; m < tenantRadios.length; m++) {
                tenantRadios[m].addEventListener('change', function () {
                    if (this.checked) {
                        var row = this.closest('tr');
                        var tenantName = row ? (row.getAttribute('data-tenant-name') || '') : '';
                        syncTenantSelection(this.value || '', tenantName);
                    }
                });
            }

            for (var n = 0; n < tenantRows.length; n++) {
                tenantRows[n].addEventListener('click', function (event) {
                    var tag = (event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '');
                    if (tag !== 'input') {
                        var radio = this.querySelector('.tenant-picker');
                        if (radio) {
                            radio.checked = true;
                            syncTenantSelection(radio.value || '', this.getAttribute('data-tenant-name') || '');
                        }
                        fillSummaryFromRow(this);
                        openModal('subscription-summary-modal');
                    }
                });
            }

            if (openPlanUpdateBtn) {
                openPlanUpdateBtn.addEventListener('click', function () {
                    if (!this.disabled) {
                        openModal('subscription-plan-modal');
                    }
                });
            }

            if (summaryEditPlanBtn) {
                summaryEditPlanBtn.addEventListener('click', function () {
                    closeModalById('subscription-summary-modal');
                    openModal('subscription-plan-modal');
                });
            }

            if (summaryStatusBtn) {
                summaryStatusBtn.addEventListener('click', function () {
                    closeModalById('subscription-summary-modal');
                    openModal('subscription-status-modal');
                });
            }

            syncTenantSelection('', '');
        })();
    </script>
    <?php endif; ?>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
