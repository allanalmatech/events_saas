<?php
require_once __DIR__ . '/../../includes/functions.php';
require_director();

$pageTitle = 'Subscription Plans';
$moduleKey = 'plans';
$modulePermission = 'dashboard.view';
$moduleDescription = 'Configure Basic, Intermediate, and Pro pricing, limits, and feature access.';

$contentRenderer = function (): void {
    $rows = [];
    if ($mysqli = db_try()) {
        $q = $mysqli->query('SELECT id, plan_name, plan_key, price_monthly, price_quarterly, price_semiannual, price_annual, max_users, max_events_per_month, status FROM subscription_plans ORDER BY id');
        $rows = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];
    }
    ?>
    <style>
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:12px; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:860px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .plan-form { display:block; }
        .plan-form .field { margin-bottom:12px; }
        .plan-price-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
        .plan-limit-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
        .plan-section-title { margin:4px 0 8px; font-size:13px; color:var(--muted); text-transform:uppercase; letter-spacing:0.4px; }
        @media (max-width: 760px) {
            .plan-price-grid,
            .plan-limit-grid { grid-template-columns:1fr; }
        }
    </style>

    <section class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Plan Matrix</h3>
            <button class="btn btn-primary" type="button" data-modal-open="plan-add-modal">+ New Plan</button>
        </div>
        <table class="table">
            <thead><tr><th>Plan</th><th>Monthly</th><th>Quarterly</th><th>Semiannual</th><th>Annual</th><th>Users</th><th>Events/Month</th><th>Edit</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo e($row['plan_name']); ?> <span class="muted">(<?php echo e($row['plan_key']); ?>)</span></td>
                    <td><?php echo number_format((float) $row['price_monthly'], 0); ?></td>
                    <td><?php echo number_format((float) $row['price_quarterly'], 0); ?></td>
                    <td><?php echo number_format((float) $row['price_semiannual'], 0); ?></td>
                    <td><?php echo number_format((float) $row['price_annual'], 0); ?></td>
                    <td><?php echo $row['max_users'] === null ? 'Unlimited' : (int) $row['max_users']; ?></td>
                    <td><?php echo $row['max_events_per_month'] === null ? 'Unlimited' : (int) $row['max_events_per_month']; ?></td>
                    <td><button class="btn btn-ghost" type="button" data-modal-open="plan-edit-<?php echo (int) $row['id']; ?>" title="Edit plan"><i class="fa-solid fa-pencil"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <div class="modal-backdrop" id="plan-add-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Create New Plan</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_plan.php')); ?>" class="plan-form">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="plan_id" value="0">
                <div class="field"><label>Plan Key</label><input name="plan_key" placeholder="basic" required></div>
                <div class="field"><label>Plan Name</label><input name="plan_name" placeholder="Basic" required></div>
                <div class="plan-section-title">Pricing</div>
                <div class="plan-price-grid">
                    <div class="field"><label>Monthly</label><input name="price_monthly" type="number" min="0" step="0.01" value="0"></div>
                    <div class="field"><label>Quarterly</label><input name="price_quarterly" type="number" min="0" step="0.01" value="0"></div>
                    <div class="field"><label>Semiannual</label><input name="price_semiannual" type="number" min="0" step="0.01" value="0"></div>
                    <div class="field"><label>Annual</label><input name="price_annual" type="number" min="0" step="0.01" value="0"></div>
                </div>
                <div class="plan-section-title">Limits</div>
                <div class="plan-limit-grid">
                    <div class="field"><label>Max Users</label><input name="max_users" type="number" min="0"></div>
                    <div class="field"><label>Max Events/Month</label><input name="max_events_per_month" type="number" min="0"></div>
                </div>
                <div class="field"><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <button class="btn btn-primary" type="submit">Save Plan</button>
            </form>
        </div>
    </div>

    <?php foreach ($rows as $row): ?>
    <div class="modal-backdrop" id="plan-edit-<?php echo (int) $row['id']; ?>">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Edit Plan: <?php echo e($row['plan_name']); ?></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_plan.php')); ?>" class="plan-form">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="plan_id" value="<?php echo (int) $row['id']; ?>">
                <div class="field"><label>Plan Key</label><input name="plan_key" value="<?php echo e($row['plan_key']); ?>" required></div>
                <div class="field"><label>Plan Name</label><input name="plan_name" value="<?php echo e($row['plan_name']); ?>" required></div>
                <div class="plan-section-title">Pricing</div>
                <div class="plan-price-grid">
                    <div class="field"><label>Monthly</label><input name="price_monthly" type="number" min="0" step="0.01" value="<?php echo e((string) $row['price_monthly']); ?>"></div>
                    <div class="field"><label>Quarterly</label><input name="price_quarterly" type="number" min="0" step="0.01" value="<?php echo e((string) $row['price_quarterly']); ?>"></div>
                    <div class="field"><label>Semiannual</label><input name="price_semiannual" type="number" min="0" step="0.01" value="<?php echo e((string) $row['price_semiannual']); ?>"></div>
                    <div class="field"><label>Annual</label><input name="price_annual" type="number" min="0" step="0.01" value="<?php echo e((string) $row['price_annual']); ?>"></div>
                </div>
                <div class="plan-section-title">Limits</div>
                <div class="plan-limit-grid">
                    <div class="field"><label>Max Users</label><input name="max_users" type="number" min="0" value="<?php echo e($row['max_users'] === null ? '' : (string) $row['max_users']); ?>"></div>
                    <div class="field"><label>Max Events/Month</label><input name="max_events_per_month" type="number" min="0" value="<?php echo e($row['max_events_per_month'] === null ? '' : (string) $row['max_events_per_month']); ?>"></div>
                </div>
                <div class="field"><label>Status</label><select name="status"><option value="active" <?php echo $row['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $row['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                <button class="btn btn-primary" type="submit">Update Plan</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        (function () {
            var openButtons = document.querySelectorAll('[data-modal-open]');
            var closeButtons = document.querySelectorAll('[data-modal-close]');

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
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
