<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Customers';
$moduleKey = 'customers';
$modulePermission = 'customers.view';
$moduleDescription = 'Track customer profiles, booking history, balances, and payment behavior.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $rows = [];
    $q = get_str('q');
    $status = get_str('status');

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $sql = 'SELECT id, full_name, phone, email, status, created_at FROM customers WHERE tenant_id = ?';
        $types = 'i';
        $params = [$tenantId];

        if ($q !== '') {
            $sql .= ' AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $like = '%' . $q . '%';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $sql .= ' AND status = ?';
            $types .= 's';
            $params[] = $status;
        }
        $sql .= ' ORDER BY id DESC LIMIT 60';

        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $bind = [$types];
            foreach ($params as $k => $val) {
                $bind[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    ?>
    <style>
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; align-items:end; }
        .toolbar .field { margin:0; min-width:200px; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:620px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    </style>

    <section class="card">
        <div class="toolbar">
            <form method="get" action="<?php echo e(app_url('modules/customers/index.php')); ?>" class="toolbar" style="flex:1;">
                <div class="field"><label>Search</label><input name="q" value="<?php echo e($q); ?>" placeholder="name, phone, email"></div>
                <div class="field"><label>Status</label><select name="status"><option value="">All</option><option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                <button class="btn btn-ghost" type="submit">Filter</button>
            </form>
            <button class="btn btn-primary" type="button" data-modal-open="add-customer-modal">+ Customer</button>
        </div>

        <table class="table">
            <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo e($row['full_name']); ?></td>
                    <td><?php echo e($row['phone']); ?></td>
                    <td><?php echo e($row['email']); ?></td>
                    <td><?php echo e($row['status']); ?></td>
                    <td><?php echo e($row['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="muted">No customers found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <div class="modal-backdrop" id="add-customer-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add Customer</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_customer.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Full Name</label><input name="full_name" required></div>
                <div class="field"><label>Phone</label><input name="phone" required></div>
                <div class="field"><label>Email</label><input type="email" name="email"></div>
                <div class="field"><label>Address</label><textarea name="address"></textarea></div>
                <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Customer</button>
            </form>
        </div>
    </div>

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
