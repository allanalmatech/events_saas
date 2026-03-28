<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Users';
$moduleKey = 'users';
$modulePermission = 'users.view';
$moduleDescription = 'Manage tenant users, profiles, activation states, and account access.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $users = [];
    $roles = [];
    $permissionOptions = [];
    $userPermissionMap = [];
    $q = get_str('q');
    $currentUser = auth_user() ?: [];
    $currentUserId = (int) ($currentUser['id'] ?? 0);
    $currentUserIsSuper = !empty($currentUser['is_super_admin']);

    if ($tenantId > 0 && ($mysqli = db_try())) {
        ensure_user_roles_table();

        $defaultRoles = [
            ['Store Keeper', 'Manages returns and inventory handover'],
            ['Accountant', 'Handles invoices, receipts and payments'],
            ['Manager', 'Supervises operations and approvals'],
        ];
        foreach ($defaultRoles as $preset) {
            $ins = $mysqli->prepare('INSERT INTO roles (tenant_id, role_name, role_description, is_system_role, status, created_at, updated_at) VALUES (?, ?, ?, 0, "active", NOW(), NOW()) ON DUPLICATE KEY UPDATE role_description = role_description');
            if ($ins) {
                $ins->bind_param('iss', $tenantId, $preset[0], $preset[1]);
                $ins->execute();
                $ins->close();
            }
        }

        $r = $mysqli->prepare('SELECT id, role_name FROM roles WHERE tenant_id = ? ORDER BY role_name');
        $r->bind_param('i', $tenantId);
        $r->execute();
        $roles = $r->get_result()->fetch_all(MYSQLI_ASSOC);
        $r->close();

        $permQ = $mysqli->query('SELECT id, module_key, action_key, permission_label FROM permissions ORDER BY module_key, action_key');
        while ($permQ && ($perm = $permQ->fetch_assoc())) {
            $permissionOptions[] = [
                'id' => (int) $perm['id'],
                'module' => $perm['module_key'],
                'action' => $perm['action_key'],
                'label' => $perm['permission_label'],
            ];
        }

        $userSql = 'SELECT u.id, u.full_name, u.email, u.phone, u.account_status, u.is_super_admin, u.role_id, r.role_name FROM tenant_users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.tenant_id = ?';
        $types = 'i';
        $params = [$tenantId];
        if ($q !== '') {
            $userSql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $like = '%' . $q . '%';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $userSql .= ' ORDER BY u.id DESC LIMIT 100';

        $u = $mysqli->prepare($userSql);
        if ($u) {
            $bind = [$types];
            foreach ($params as $k => $val) {
                $bind[] = &$params[$k];
            }
            call_user_func_array([$u, 'bind_param'], $bind);
            $u->execute();
            $users = $u->get_result()->fetch_all(MYSQLI_ASSOC);
            $u->close();
        }

        $userIds = [];
        foreach ($users as $user) {
            $uid = (int) $user['id'];
            $userIds[] = $uid;
            $userPermissionMap[$uid] = [];
        }

        if ($userIds) {
            $idList = implode(',', array_map('intval', $userIds));
            $res = $mysqli->query('SELECT user_id, permission_id FROM user_permissions WHERE tenant_id = ' . $tenantId . ' AND user_id IN (' . $idList . ') AND grant_type = "allow"');
            while ($res && ($row = $res->fetch_assoc())) {
                $uid = (int) $row['user_id'];
                $pid = (int) $row['permission_id'];
                if (!isset($userPermissionMap[$uid])) {
                    $userPermissionMap[$uid] = [];
                }
                $userPermissionMap[$uid][] = $pid;
            }
        }

        foreach ($userPermissionMap as $uid => $ids) {
            $userPermissionMap[$uid] = array_values(array_unique(array_map('intval', $ids)));
        }
    }
    ?>
    <style>
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; align-items:end; }
        .toolbar .field { margin:0; min-width:220px; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:680px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .dual-grid { display:grid; grid-template-columns: 1fr auto 1fr; gap:10px; align-items:start; }
        .transfer-controls { display:flex; flex-direction:column; gap:8px; }
        .picker-note { margin-top:8px; font-size:12px; color:var(--muted); }
        .select-col { width:48px; text-align:center; }
        .btn[disabled] { opacity:0.45; cursor:not-allowed; }
        .perm-panel { min-height:260px; max-height:360px; overflow:auto; border:1px solid var(--outline); border-radius:12px; padding:10px; background:rgba(15, 14, 13, 0.55); }
        .perm-group { margin-bottom:10px; }
        .perm-group-title { display:flex; align-items:center; gap:8px; font-size:12px; text-transform:uppercase; color:var(--muted); margin-bottom:6px; letter-spacing:0.4px; }
        .perm-item { display:flex; align-items:flex-start; gap:8px; margin-bottom:6px; font-size:13px; }
        .perm-item code { font-size:11px; color:var(--muted); }
        .action-cell { width:68px; }
        .users-table-wrap { width:100%; overflow-x:auto; }

        @media (max-width: 860px) {
            .toolbar { align-items:stretch; }
            .toolbar form.toolbar { width:100%; }
            .toolbar .field { min-width:100%; }
            .toolbar .btn { width:100%; }
            .dual-grid { grid-template-columns: 1fr; }
            .transfer-controls { flex-direction:row; justify-content:space-between; }

            .users-table thead { display:none; }
            .users-table,
            .users-table tbody,
            .users-table tr,
            .users-table td { display:block; width:100%; }

            .users-table tr {
                border:1px solid var(--outline);
                border-radius:12px;
                padding:10px;
                margin-bottom:10px;
                background:var(--surface-soft);
            }

            .users-table tr.no-users-row { padding:0; border:none; background:transparent; }

            .users-table td {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:10px;
                border:none;
                padding:8px 0;
            }

            .users-table td::before {
                content: attr(data-label);
                font-size:12px;
                color:var(--muted);
                text-transform:uppercase;
                letter-spacing:0.4px;
            }

            .users-table td.select-col,
            .users-table td.action-cell { text-align:left; width:100%; }

            .users-table tr.no-users-row td { display:block; }
            .users-table tr.no-users-row td::before { content:''; }
        }
    </style>

    <section class="card">
        <div class="toolbar">
            <form method="get" action="<?php echo e(app_url('modules/users/index.php')); ?>" class="toolbar" style="flex:1;">
                <div class="field"><label>Search Users</label><input name="q" value="<?php echo e($q); ?>" placeholder="name, email, phone"></div>
                <button class="btn btn-ghost" type="submit">Filter</button>
            </form>
            <button class="btn btn-primary" type="button" data-modal-open="add-user-modal">+ User</button>
            <button class="btn btn-ghost" id="open-assign-permissions" type="button" disabled>Assign Permissions</button>
        </div>

        <div class="users-table-wrap">
            <table class="table users-table">
                <thead><tr><th class="select-col">Pick</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th class="action-cell">Edit</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td class="select-col" data-label="Pick"><input type="radio" name="pick_user" class="user-picker" value="<?php echo (int) $user['id']; ?>" data-user-name="<?php echo e($user['full_name']); ?>"></td>
                    <td data-label="Name"><?php echo e($user['full_name']); ?></td>
                    <td data-label="Email"><?php echo e($user['email']); ?></td>
                    <td data-label="Phone"><?php echo e($user['phone']); ?></td>
                    <td data-label="Role"><?php echo e($user['role_name'] ?: ($user['is_super_admin'] ? 'Super Admin' : '-')); ?></td>
                    <td data-label="Status"><?php echo e($user['account_status']); ?></td>
                    <td class="action-cell" data-label="Edit">
                        <?php $canEditThisUser = $currentUserIsSuper || !(bool) $user['is_super_admin']; ?>
                        <?php if ($canEditThisUser): ?>
                            <button class="btn btn-ghost" type="button" data-modal-open="edit-user-<?php echo (int) $user['id']; ?>" title="Edit user"><i class="fa-solid fa-pencil"></i></button>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?><tr class="no-users-row"><td colspan="7" class="muted">No users found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-backdrop" id="assign-permissions-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Assign Permissions: <span id="selected-user-name">-</span></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/assign_user_permissions.php')); ?>" id="permission-assignment-form">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="user_id" id="assign-user-id" value="0">

                <div class="dual-grid">
                    <div>
                        <label>Available Permissions</label>
                        <div class="perm-panel" id="available-permissions"></div>
                    </div>

                    <div class="transfer-controls">
                        <button class="btn btn-ghost" id="assign-selected" type="button" title="Assign checked"><i class="fa-solid fa-angle-right"></i></button>
                        <button class="btn btn-ghost" id="assign-all" type="button" title="Assign all"><i class="fa-solid fa-angles-right"></i></button>
                        <button class="btn btn-ghost" id="remove-selected" type="button" title="Remove checked"><i class="fa-solid fa-angle-left"></i></button>
                        <button class="btn btn-ghost" id="remove-all" type="button" title="Remove all"><i class="fa-solid fa-angles-left"></i></button>
                    </div>

                    <div>
                        <label>Assigned Permissions</label>
                        <div class="perm-panel" id="assigned-permissions"></div>
                    </div>
                </div>
                <p class="picker-note">This sets direct user permissions for page/module access rights.</p>
                <div id="assigned-permission-inputs"></div>
                <button class="btn btn-primary" type="submit">Save User Permissions</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="add-user-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add User</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_user.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Full Name</label><input name="full_name" required></div>
                <div class="field"><label>Email</label><input type="email" name="email" required></div>
                <div class="field"><label>Phone</label><input name="phone"></div>
                <div class="field"><label>Role</label>
                    <select name="role_id">
                        <option value="0">Custom (use field below)</option>
                        <?php foreach ($roles as $role): ?><option value="<?php echo (int) $role['id']; ?>"><?php echo e($role['role_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Custom Role Name (optional)</label><input name="custom_role_name" placeholder="e.g. Operations Lead"></div>
                <div class="field"><label>Password</label><input type="password" name="password" minlength="8" required></div>
                <button class="btn btn-primary" type="submit">Create User</button>
            </form>
        </div>
    </div>

    <?php foreach ($users as $user): ?>
        <?php
        $targetUserId = (int) $user['id'];
        $targetIsSuper = (bool) $user['is_super_admin'];
        $canEditThisUser = $currentUserIsSuper || !$targetIsSuper;
        if (!$canEditThisUser) {
            continue;
        }
        $canDeleteThisUser = $targetUserId !== $currentUserId && ($currentUserIsSuper || !$targetIsSuper);
        ?>
        <div class="modal-backdrop" id="edit-user-<?php echo (int) $user['id']; ?>">
            <div class="card modal-card">
                <div class="modal-header"><h3 style="margin:0;">Edit User: <?php echo e($user['full_name']); ?></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                <form method="post" action="<?php echo e(app_url('actions/update_user.php')); ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <div class="field"><label>Full Name</label><input name="full_name" value="<?php echo e($user['full_name']); ?>" required></div>
                    <div class="field"><label>Email</label><input type="email" name="email" value="<?php echo e($user['email']); ?>" required></div>
                    <div class="field"><label>Phone</label><input name="phone" value="<?php echo e($user['phone']); ?>"></div>
                    <div class="field"><label>Role</label>
                        <select name="role_id">
                            <option value="0">Custom (use field below)</option>
                            <?php foreach ($roles as $role): ?><option value="<?php echo (int) $role['id']; ?>" <?php echo (int) $user['role_id'] === (int) $role['id'] ? 'selected' : ''; ?>><?php echo e($role['role_name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Custom Role Name (optional)</label><input name="custom_role_name" placeholder="Use only when custom role is selected"></div>
                    <div class="field"><label>Status</label><select name="account_status"><option value="active" <?php echo $user['account_status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $user['account_status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                    <button class="btn btn-primary" type="submit">Update User</button>
                </form>

                <form method="post" action="<?php echo e(app_url('actions/delete_user.php')); ?>" style="margin-top:10px;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                    <button class="btn btn-ghost" type="submit" <?php echo $canDeleteThisUser ? '' : 'disabled'; ?> data-confirm="Delete this user? If linked to records, it will be deactivated instead.">Delete User</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="modal-backdrop" id="add-role-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add Role</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_role.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Role Name</label><input name="role_name" placeholder="Custom role" required></div>
                <div class="field"><label>Description</label><textarea name="role_description" placeholder="What this role can do"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Role</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var permissions = <?php echo json_encode($permissionOptions); ?> || [];
            var userPermissionMap = <?php echo json_encode($userPermissionMap); ?> || {};

            var selectedUserId = null;
            var selectedUserName = '';

            var openButtons = document.querySelectorAll('[data-modal-open]');
            var closeButtons = document.querySelectorAll('[data-modal-close]');
            var assignPermissionsBtn = document.getElementById('open-assign-permissions');
            var assignModal = document.getElementById('assign-permissions-modal');
            var selectedUserNameEl = document.getElementById('selected-user-name');
            var assignUserIdInput = document.getElementById('assign-user-id');
            var available = document.getElementById('available-permissions');
            var assigned = document.getElementById('assigned-permissions');
            var assignSelectedBtn = document.getElementById('assign-selected');
            var assignAllBtn = document.getElementById('assign-all');
            var removeSelectedBtn = document.getElementById('remove-selected');
            var removeAllBtn = document.getElementById('remove-all');
            var permissionForm = document.getElementById('permission-assignment-form');
            var assignedInputs = document.getElementById('assigned-permission-inputs');

            var permissionState = {
                available: [],
                assigned: []
            };

            var moduleIcons = {
                users: 'fa-users',
                roles: 'fa-user-shield',
                permissions: 'fa-key',
                customers: 'fa-address-book',
                items: 'fa-boxes-stacked',
                services: 'fa-screwdriver-wrench',
                bookings: 'fa-calendar-check',
                returns: 'fa-rotate-left',
                invoices: 'fa-file-invoice-dollar',
                receipts: 'fa-receipt',
                payments: 'fa-wallet',
                workers: 'fa-people-carry-box',
                calendar: 'fa-calendar-days',
                notifications: 'fa-bell',
                messages: 'fa-comments',
                broadcasts: 'fa-bullhorn',
                marketplace: 'fa-store',
                tickets: 'fa-life-ring',
                reports: 'fa-chart-line',
                settings: 'fa-gear',
                audit_logs: 'fa-clipboard-list',
                subscriptions: 'fa-arrows-rotate',
                dashboard: 'fa-gauge-high'
            };

            function getPermissionById(id) {
                for (var i = 0; i < permissions.length; i++) {
                    if (String(permissions[i].id) === String(id)) {
                        return permissions[i];
                    }
                }
                return null;
            }

            function uniqueIds(ids) {
                var map = {};
                var out = [];
                for (var i = 0; i < ids.length; i++) {
                    var v = String(ids[i]);
                    if (!map[v]) {
                        map[v] = true;
                        out.push(v);
                    }
                }
                return out;
            }

            function initPermissionState(userId) {
                var ids = userPermissionMap[String(userId)] || [];
                var assignedMap = {};
                for (var i = 0; i < ids.length; i++) {
                    assignedMap[String(ids[i])] = true;
                }

                permissionState.available = [];
                permissionState.assigned = [];

                for (var j = 0; j < permissions.length; j++) {
                    var permission = permissions[j];
                    if (assignedMap[String(permission.id)]) {
                        permissionState.assigned.push(String(permission.id));
                    } else {
                        permissionState.available.push(String(permission.id));
                    }
                }

                permissionState.available = uniqueIds(permissionState.available);
                permissionState.assigned = uniqueIds(permissionState.assigned);
                renderPermissionPanels();
            }

            function moduleLabel(moduleKey) {
                return moduleKey.replace(/_/g, ' ');
            }

            function renderPanel(targetEl, ids, type) {
                if (!targetEl) {
                    return;
                }
                targetEl.innerHTML = '';

                var grouped = {};
                for (var i = 0; i < ids.length; i++) {
                    var perm = getPermissionById(ids[i]);
                    if (!perm) {
                        continue;
                    }
                    var moduleKey = perm.module || 'other';
                    if (!grouped[moduleKey]) {
                        grouped[moduleKey] = [];
                    }
                    grouped[moduleKey].push(perm);
                }

                var modules = Object.keys(grouped).sort();
                if (!modules.length) {
                    targetEl.innerHTML = '<div class="muted">No permissions here.</div>';
                    return;
                }

                for (var m = 0; m < modules.length; m++) {
                    var key = modules[m];
                    var box = document.createElement('div');
                    box.className = 'perm-group';

                    var title = document.createElement('div');
                    title.className = 'perm-group-title';
                    var icon = moduleIcons[key] || 'fa-list-check';
                    title.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + moduleLabel(key);
                    box.appendChild(title);

                    for (var p = 0; p < grouped[key].length; p++) {
                        var perm = grouped[key][p];
                        var item = document.createElement('label');
                        item.className = 'perm-item';
                        item.innerHTML = '<input type="checkbox" class="perm-check" data-list="' + type + '" value="' + perm.id + '">'
                            + '<span><strong>' + perm.label + '</strong><br><code>' + perm.module + '.' + perm.action + '</code></span>';
                        box.appendChild(item);
                    }

                    targetEl.appendChild(box);
                }
            }

            function renderPermissionPanels() {
                renderPanel(available, permissionState.available, 'available');
                renderPanel(assigned, permissionState.assigned, 'assigned');
            }

            function getCheckedIds(listType) {
                var root = listType === 'available' ? available : assigned;
                if (!root) {
                    return [];
                }
                var checks = root.querySelectorAll('.perm-check:checked');
                var ids = [];
                for (var i = 0; i < checks.length; i++) {
                    ids.push(String(checks[i].value));
                }
                return uniqueIds(ids);
            }

            function moveChecked(fromType, toType) {
                var moving = getCheckedIds(fromType);
                if (!moving.length) {
                    return;
                }
                var from = permissionState[fromType].filter(function (id) {
                    return moving.indexOf(String(id)) === -1;
                });
                var to = permissionState[toType].concat(moving);
                permissionState[fromType] = uniqueIds(from);
                permissionState[toType] = uniqueIds(to);
                renderPermissionPanels();
            }

            function moveAll(fromType, toType) {
                if (!permissionState[fromType].length) {
                    return;
                }
                permissionState[toType] = uniqueIds(permissionState[toType].concat(permissionState[fromType]));
                permissionState[fromType] = [];
                renderPermissionPanels();
            }

            function openModal(id) {
                var modal = document.getElementById(id);
                if (modal) {
                    modal.classList.add('open');
                }
            }

            function closeModal(el) {
                var modal = el.closest('.modal-backdrop');
                if (modal) {
                    modal.classList.remove('open');
                }
            }

            for (var i = 0; i < openButtons.length; i++) {
                openButtons[i].addEventListener('click', function () {
                    openModal(this.getAttribute('data-modal-open'));
                });
            }

            for (var j = 0; j < closeButtons.length; j++) {
                closeButtons[j].addEventListener('click', function () {
                    closeModal(this);
                });
            }

            var backdrops = document.querySelectorAll('.modal-backdrop');
            for (var k = 0; k < backdrops.length; k++) {
                backdrops[k].addEventListener('click', function (event) {
                    if (event.target === this) {
                        this.classList.remove('open');
                    }
                });
            }

            var pickers = document.querySelectorAll('.user-picker');
            for (var p = 0; p < pickers.length; p++) {
                pickers[p].addEventListener('change', function () {
                    selectedUserId = parseInt(this.value, 10);
                    selectedUserName = this.getAttribute('data-user-name') || '';
                    if (assignPermissionsBtn) {
                        assignPermissionsBtn.disabled = !selectedUserId;
                    }
                });
            }

            if (assignPermissionsBtn) {
                assignPermissionsBtn.addEventListener('click', function () {
                    if (!selectedUserId) {
                        return;
                    }

                    if (selectedUserNameEl) {
                        selectedUserNameEl.textContent = selectedUserName;
                    }
                    if (assignUserIdInput) {
                        assignUserIdInput.value = String(selectedUserId);
                    }

                    initPermissionState(selectedUserId);
                    if (assignModal) {
                        assignModal.classList.add('open');
                    }
                });
            }

            if (assignSelectedBtn) {
                assignSelectedBtn.addEventListener('click', function () {
                    moveChecked('available', 'assigned');
                });
            }

            if (assignAllBtn) {
                assignAllBtn.addEventListener('click', function () {
                    moveAll('available', 'assigned');
                });
            }

            if (removeSelectedBtn) {
                removeSelectedBtn.addEventListener('click', function () {
                    moveChecked('assigned', 'available');
                });
            }

            if (removeAllBtn) {
                removeAllBtn.addEventListener('click', function () {
                    moveAll('assigned', 'available');
                });
            }

            if (permissionForm) {
                permissionForm.addEventListener('submit', function () {
                    if (!assignedInputs) {
                        return;
                    }
                    assignedInputs.innerHTML = '';
                    for (var i = 0; i < permissionState.assigned.length; i++) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'assigned_permission_ids[]';
                        input.value = String(permissionState.assigned[i]);
                        assignedInputs.appendChild(input);
                    }
                });
            }
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
