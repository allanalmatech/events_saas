<?php
require_once __DIR__ . '/../../includes/functions.php';
require_director();

$pageTitle = 'Tenant Management';
$moduleKey = 'tenants';
$modulePermission = 'dashboard.view';
$moduleDescription = 'Approve/reject business accounts, monitor tenant status, and enforce lifecycle controls.';

$contentRenderer = function (): void {
    $rows = [];
    if ($mysqli = db_try()) {
        $q = $mysqli->query('SELECT id, business_name, email, phone, account_status, created_at FROM tenants ORDER BY id DESC LIMIT 50');
        $rows = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];
    }
    ?>
    <section class="card">
        <h3 style="margin-top:0;">Tenant Queue</h3>
        <table class="table">
            <thead><tr><th>Business</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo e($row['business_name']); ?></td>
                    <td><?php echo e($row['email']); ?></td>
                    <td><?php echo e($row['account_status']); ?></td>
                    <td>
                        <form method="post" action="<?php echo e(app_url('actions/approve_tenant.php')); ?>" style="display:inline-block;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="tenant_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="status" value="active">
                            <button class="btn btn-primary" type="submit">Approve</button>
                        </form>
                        <form method="post" action="<?php echo e(app_url('actions/lock_tenant.php')); ?>" style="display:inline-block;">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="tenant_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="lock_mode" value="soft">
                            <button class="btn btn-ghost" type="submit">Lock</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="4" class="muted">No tenants found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
