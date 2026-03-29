<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Audit Logs';
$moduleKey = 'audit_logs';
$modulePermission = 'audit_logs.view';
$moduleDescription = 'Trace every critical action from login to operational changes for accountability.';

$contentRenderer = function (): void {
    $isDirector = auth_role() === 'director';
    $rows = [];
    $tenantId = current_tenant_id();
    $moduleFilter = get_str('module_key');
    $actionFilter = get_str('action_key');
    $roleFilter = get_str('actor_role');
    $dateFrom = get_str('date_from');
    $dateTo = get_str('date_to');

    $moduleOptions = [];
    $actionOptions = [];
    $roleOptions = [];

    $bindDynamic = static function ($stmt, string $types, array &$params): void {
        if ($types === '' || !$params) {
            return;
        }
        $bind = [$types];
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    };

    if ($mysqli = db_try()) {
        if ($isDirector) {
            $modQ = $mysqli->query('SELECT DISTINCT module_key FROM audit_logs WHERE module_key IS NOT NULL AND module_key <> "" ORDER BY module_key ASC LIMIT 200');
            $moduleOptions = $modQ ? $modQ->fetch_all(MYSQLI_ASSOC) : [];
            $actQ = $mysqli->query('SELECT DISTINCT action_key FROM audit_logs WHERE action_key IS NOT NULL AND action_key <> "" ORDER BY action_key ASC LIMIT 200');
            $actionOptions = $actQ ? $actQ->fetch_all(MYSQLI_ASSOC) : [];
            $roleQ = $mysqli->query('SELECT DISTINCT actor_role FROM audit_logs WHERE actor_role IS NOT NULL AND actor_role <> "" ORDER BY actor_role ASC LIMIT 100');
            $roleOptions = $roleQ ? $roleQ->fetch_all(MYSQLI_ASSOC) : [];
        } elseif ($tenantId) {
            $tid = (int) $tenantId;

            $modStmt = $mysqli->prepare('SELECT DISTINCT module_key FROM audit_logs WHERE tenant_id = ? AND module_key IS NOT NULL AND module_key <> "" ORDER BY module_key ASC LIMIT 200');
            if ($modStmt) {
                $modStmt->bind_param('i', $tid);
                $modStmt->execute();
                $moduleOptions = $modStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $modStmt->close();
            }

            $actStmt = $mysqli->prepare('SELECT DISTINCT action_key FROM audit_logs WHERE tenant_id = ? AND action_key IS NOT NULL AND action_key <> "" ORDER BY action_key ASC LIMIT 200');
            if ($actStmt) {
                $actStmt->bind_param('i', $tid);
                $actStmt->execute();
                $actionOptions = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $actStmt->close();
            }

            $roleStmt = $mysqli->prepare('SELECT DISTINCT actor_role FROM audit_logs WHERE tenant_id = ? AND actor_role IS NOT NULL AND actor_role <> "" ORDER BY actor_role ASC LIMIT 100');
            if ($roleStmt) {
                $roleStmt->bind_param('i', $tid);
                $roleStmt->execute();
                $roleOptions = $roleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $roleStmt->close();
            }
        }

        $sql = 'SELECT actor_role, module_key, action_key, record_table, record_id, ip_address, created_at FROM audit_logs WHERE 1=1';
        $types = '';
        $params = [];

        if (!$isDirector) {
            $sql .= ' AND tenant_id = ?';
            $types .= 'i';
            $params[] = (int) $tenantId;
        }
        if ($moduleFilter !== '') {
            $sql .= ' AND module_key = ?';
            $types .= 's';
            $params[] = $moduleFilter;
        }
        if ($actionFilter !== '') {
            $sql .= ' AND action_key = ?';
            $types .= 's';
            $params[] = $actionFilter;
        }
        if ($roleFilter !== '') {
            $sql .= ' AND actor_role = ?';
            $types .= 's';
            $params[] = $roleFilter;
        }
        if ($dateFrom !== '') {
            $sql .= ' AND DATE(created_at) >= ?';
            $types .= 's';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND DATE(created_at) <= ?';
            $types .= 's';
            $params[] = $dateTo;
        }

        $sql .= ' ORDER BY id DESC LIMIT 200';
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $bindDynamic($stmt, $types, $params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    ?>
    <style>
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; align-items:end; }
        .toolbar .field { margin:0; min-width:160px; }
        .audit-filter-panel { margin-bottom:12px; }
        .audit-filter-toggle { display:none; }
        .audit-table-wrap { width:100%; overflow-x:auto; }

        @media (max-width: 860px) {
            .toolbar { align-items:stretch; }
            .toolbar form.toolbar { width:100%; }
            .toolbar .field { min-width:100%; }
            .toolbar .btn { width:100%; }
            .audit-filter-toggle { display:inline-flex; width:100%; }
            .audit-filter-panel { display:none; }
            .audit-filter-panel.open { display:block; }

            .audit-table thead { display:none; }
            .audit-table,
            .audit-table tbody,
            .audit-table tr,
            .audit-table td { display:block; width:100%; }

            .audit-table tr {
                border:1px solid var(--outline);
                border-radius:12px;
                padding:10px;
                margin-bottom:10px;
                background:var(--surface-soft);
            }

            .audit-table tr.no-audit-row {
                border:none;
                padding:0;
                margin:0;
                background:transparent;
            }

            .audit-table td {
                display:flex;
                justify-content:space-between;
                gap:10px;
                border:none;
                padding:8px 0;
                text-align:right;
            }

            .audit-table td::before {
                content: attr(data-label);
                color:var(--muted);
                font-size:12px;
                text-transform:uppercase;
                letter-spacing:0.3px;
                text-align:left;
            }

            .audit-table tr.no-audit-row td { display:block; text-align:left; }
            .audit-table tr.no-audit-row td::before { content: ''; }
        }
    </style>
    <section class="card">
        <div class="toolbar">
            <button class="btn btn-ghost audit-filter-toggle" type="button" id="audit-filter-toggle" aria-expanded="false"><i class="fa-solid fa-magnifying-glass"></i> Search & Filters</button>
        </div>

        <div class="audit-filter-panel" id="audit-filter-panel">
            <form method="get" class="toolbar" style="margin-bottom:0; display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; align-items:end;">
                <div class="field"><label>Module</label><select name="module_key"><option value="">All</option><?php foreach ($moduleOptions as $opt): ?><option value="<?php echo e($opt['module_key']); ?>" <?php echo $moduleFilter === $opt['module_key'] ? 'selected' : ''; ?>><?php echo e($opt['module_key']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Action</label><select name="action_key"><option value="">All</option><?php foreach ($actionOptions as $opt): ?><option value="<?php echo e($opt['action_key']); ?>" <?php echo $actionFilter === $opt['action_key'] ? 'selected' : ''; ?>><?php echo e($opt['action_key']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Role</label><select name="actor_role"><option value="">All</option><?php foreach ($roleOptions as $opt): ?><option value="<?php echo e($opt['actor_role']); ?>" <?php echo $roleFilter === $opt['actor_role'] ? 'selected' : ''; ?>><?php echo e($opt['actor_role']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>From</label><input type="date" name="date_from" value="<?php echo e($dateFrom); ?>"></div>
                <div class="field"><label>To</label><input type="date" name="date_to" value="<?php echo e($dateTo); ?>"></div>
                <button class="btn btn-primary" type="submit">Filter</button>
            </form>
        </div>

        <?php if ($isDirector): ?>
            <form method="post" action="<?php echo e(app_url('actions/delete_audit_logs.php')); ?>" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; align-items:end; margin-bottom:12px;">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Delete From</label><input type="date" name="delete_from" required></div>
                <div class="field"><label>Delete To</label><input type="date" name="delete_to" required></div>
                <button class="btn btn-ghost" type="submit" data-confirm="Delete audit logs in this date range? This cannot be undone.">Delete Old Logs</button>
            </form>
        <?php endif; ?>

        <div class="audit-table-wrap">
            <table class="table audit-table">
                <thead><tr><th>Role</th><th>Module</th><th>Action</th><th>Record</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Role"><?php echo e($row['actor_role']); ?></td>
                        <td data-label="Module"><?php echo e($row['module_key']); ?></td>
                        <td data-label="Action"><?php echo e($row['action_key']); ?></td>
                        <td data-label="Record"><?php echo e($row['record_table'] . ':' . $row['record_id']); ?></td>
                        <td data-label="IP"><?php echo e($row['ip_address']); ?></td>
                        <td data-label="Time"><?php echo e($row['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr class="no-audit-row"><td colspan="6" class="muted">No audit records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <script>
        (function () {
            var filterToggle = document.getElementById('audit-filter-toggle');
            var filterPanel = document.getElementById('audit-filter-panel');
            if (!filterToggle || !filterPanel) {
                return;
            }
            filterToggle.addEventListener('click', function () {
                var isOpen = filterPanel.classList.toggle('open');
                filterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
