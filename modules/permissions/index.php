<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Permissions';
$moduleKey = 'permissions';
$modulePermission = 'permissions.view';
$moduleDescription = 'Assign module/action permissions to roles and users.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $roles = [];
    $permissions = [];
    $users = [];

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $r = $mysqli->prepare('SELECT id, role_name FROM roles WHERE tenant_id = ? ORDER BY role_name');
        $r->bind_param('i', $tenantId);
        $r->execute();
        $roles = $r->get_result()->fetch_all(MYSQLI_ASSOC);
        $r->close();

        $q = $mysqli->query('SELECT id, module_key, action_key, permission_label FROM permissions ORDER BY module_key, action_key');
        $permissions = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];

        $u = $mysqli->prepare('SELECT id, full_name FROM tenant_users WHERE tenant_id = ? ORDER BY full_name');
        $u->bind_param('i', $tenantId);
        $u->execute();
        $users = $u->get_result()->fetch_all(MYSQLI_ASSOC);
        $u->close();
    }
    ?>
    <section class="card" style="margin-bottom:14px;">
        <h3 style="margin-top:0;">Assign Role Permissions</h3>
        <form method="post" action="<?php echo e(app_url('actions/assign_permissions.php')); ?>">
            <?php echo csrf_input(); ?>
            <div class="field"><label>Role</label><select name="role_id"><?php foreach ($roles as $role): ?><option value="<?php echo (int) $role['id']; ?>"><?php echo e($role['role_name']); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Permissions</label>
                <div style="max-height:280px;overflow:auto;padding:10px;border:1px solid var(--outline);border-radius:12px;">
                    <?php foreach ($permissions as $permission): ?>
                        <label style="display:block;margin-bottom:5px;">
                            <input type="checkbox" name="permission_ids[]" value="<?php echo (int) $permission['id']; ?>">
                            <?php echo e($permission['module_key'] . '.' . $permission['action_key']); ?> - <?php echo e($permission['permission_label']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Save Permissions</button>
        </form>
    </section>

    <section class="card">
        <h3 style="margin-top:0;">User Permission Override (Optional)</h3>
        <form method="post" action="<?php echo e(app_url('actions/save_user_override.php')); ?>" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end;">
            <?php echo csrf_input(); ?>
            <div class="field"><label>User</label><select name="user_id"><?php foreach ($users as $user): ?><option value="<?php echo (int) $user['id']; ?>"><?php echo e($user['full_name']); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Permission</label><select name="permission_id"><?php foreach ($permissions as $permission): ?><option value="<?php echo (int) $permission['id']; ?>"><?php echo e($permission['module_key'] . '.' . $permission['action_key']); ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Grant Type</label><select name="grant_type"><option value="allow">Allow</option><option value="deny">Deny</option></select></div>
            <button class="btn btn-primary" type="submit">Save Override</button>
        </form>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
