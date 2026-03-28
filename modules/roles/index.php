<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Roles';
$moduleKey = 'roles';
$modulePermission = 'roles.view';
$moduleDescription = 'Create and maintain role structures for tenant RBAC.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $rows = [];
    if ($tenantId > 0 && ($mysqli = db_try())) {
        $stmt = $mysqli->prepare('SELECT id, role_name, role_description, status FROM roles WHERE tenant_id = ? ORDER BY id DESC');
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    ?>
    <section class="grid cols-2">
        <article class="card">
            <h3 style="margin-top:0;">Create Role</h3>
            <form method="post" action="<?php echo e(app_url('actions/save_role.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Role Name</label><input name="role_name" required></div>
                <div class="field"><label>Description</label><textarea name="role_description"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Role</button>
            </form>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Role List</h3>
            <table class="table">
                <thead><tr><th>Role</th><th>Description</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr><td><?php echo e($row['role_name']); ?></td><td><?php echo e($row['role_description']); ?></td><td><?php echo e($row['status']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="3" class="muted">No roles yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
