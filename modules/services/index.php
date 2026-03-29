<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Services';
$moduleKey = 'services';
$modulePermission = 'services.view';
$moduleDescription = 'Define offered services, pricing models, and package groupings.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $rows = [];
    $q = get_str('q');
    $pricingType = get_str('pricing_type');
    $status = get_str('status');

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $sql = 'SELECT s.id, s.service_name, s.pricing_type, s.price, s.description, s.status, s.created_at,
                EXISTS(SELECT 1 FROM booking_services bs WHERE bs.tenant_id = s.tenant_id AND bs.service_id = s.id) AS in_booking,
                EXISTS(SELECT 1 FROM invoice_items ii WHERE ii.tenant_id = s.tenant_id AND ii.line_type = "service" AND ii.reference_id = s.id) AS in_invoice
                FROM services s
                WHERE s.tenant_id = ?';
        $types = 'i';
        $params = [$tenantId];

        if ($q !== '') {
            $sql .= ' AND (service_name LIKE ? OR description LIKE ?)';
            $like = '%' . $q . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if (in_array($pricingType, ['flat', 'unit'], true)) {
            $sql .= ' AND pricing_type = ?';
            $types .= 's';
            $params[] = $pricingType;
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
        .toolbar .field { margin:0; min-width:180px; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:620px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .action-col { width:70px; }
        .services-table-wrap { width:100%; overflow-x:auto; }

        @media (max-width: 860px) {
            .toolbar { align-items:stretch; }
            .toolbar form.toolbar { width:100%; }
            .toolbar .field { min-width:100%; }
            .toolbar .btn { width:100%; }

            .services-table thead { display:none; }
            .services-table,
            .services-table tbody,
            .services-table tr,
            .services-table td { display:block; width:100%; }

            .services-table tr {
                border:1px solid var(--outline);
                border-radius:12px;
                padding:10px;
                margin-bottom:10px;
                background:var(--surface-soft);
            }

            .services-table tr.no-services-row {
                border:none;
                padding:0;
                margin:0;
                background:transparent;
            }

            .services-table td {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:10px;
                border:none;
                padding:8px 0;
                text-align:right;
            }

            .services-table td::before {
                content: attr(data-label);
                font-size:12px;
                color:var(--muted);
                text-transform:uppercase;
                letter-spacing:0.4px;
                text-align:left;
            }

            .services-table tr.no-services-row td {
                display:block;
                text-align:left;
            }

            .services-table tr.no-services-row td::before {
                content: '';
            }
        }
    </style>

    <section class="card">
        <div class="toolbar">
            <form method="get" action="<?php echo e(app_url('modules/services/index.php')); ?>" class="toolbar" style="flex:1;">
                <div class="field"><label>Search</label><input name="q" value="<?php echo e($q); ?>" placeholder="service name"></div>
                <div class="field"><label>Pricing Type</label><select name="pricing_type"><option value="">All</option><option value="flat" <?php echo $pricingType === 'flat' ? 'selected' : ''; ?>>Flat</option><option value="unit" <?php echo $pricingType === 'unit' ? 'selected' : ''; ?>>Per Unit</option></select></div>
                <div class="field"><label>Status</label><select name="status"><option value="">All</option><option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                <button class="btn btn-ghost" type="submit">Filter</button>
            </form>
            <button class="btn btn-primary" type="button" data-modal-open="add-service-modal">+ Service</button>
        </div>

        <div class="services-table-wrap">
            <table class="table services-table">
                <thead><tr><th>Name</th><th>Type</th><th>Price</th><th>Status</th><th>Created</th><th class="action-col">Edit</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Name"><?php echo e($row['service_name']); ?></td>
                        <td data-label="Type"><?php echo e($row['pricing_type']); ?></td>
                        <td data-label="Price"><?php echo number_format((float) $row['price'], 2); ?></td>
                        <td data-label="Status"><?php echo e($row['status']); ?></td>
                        <td data-label="Created"><?php echo e($row['created_at']); ?></td>
                        <td data-label="Edit"><button class="btn btn-ghost" type="button" data-modal-open="edit-service-<?php echo (int) $row['id']; ?>" title="Edit service"><i class="fa-solid fa-pencil"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr class="no-services-row"><td colspan="6" class="muted">No services found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-backdrop" id="add-service-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add Service</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_service.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Service Name</label><input name="service_name" required></div>
                <div class="field"><label>Price</label><input type="number" step="0.01" min="0" name="price" value="0"></div>
                <div class="field"><label>Pricing Type</label><select name="pricing_type"><option value="flat">Flat</option><option value="unit">Per Unit</option></select></div>
                <div class="field"><label>Description</label><textarea name="description"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Service</button>
            </form>
        </div>
    </div>

    <?php foreach ($rows as $row): ?>
        <?php $inUse = !empty($row['in_booking']) || !empty($row['in_invoice']); ?>
        <div class="modal-backdrop" id="edit-service-<?php echo (int) $row['id']; ?>">
            <div class="card modal-card">
                <div class="modal-header"><h3 style="margin:0;">Edit Service: <?php echo e($row['service_name']); ?></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                <form method="post" action="<?php echo e(app_url('actions/update_service.php')); ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="service_id" value="<?php echo (int) $row['id']; ?>">
                    <div class="field"><label>Service Name</label><input name="service_name" value="<?php echo e($row['service_name']); ?>" required></div>
                    <div class="field"><label>Price</label><input type="number" step="0.01" min="0" name="price" value="<?php echo e((string) $row['price']); ?>"></div>
                    <div class="field"><label>Pricing Type</label><select name="pricing_type"><option value="flat" <?php echo $row['pricing_type'] === 'flat' ? 'selected' : ''; ?>>Flat</option><option value="unit" <?php echo $row['pricing_type'] === 'unit' ? 'selected' : ''; ?>>Per Unit</option></select></div>
                    <div class="field"><label>Status</label><select name="status"><option value="active" <?php echo $row['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $row['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                    <div class="field"><label>Description</label><textarea name="description"><?php echo e($row['description']); ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Update Service</button>
                </form>

                <form method="post" action="<?php echo e(app_url('actions/delete_service.php')); ?>" style="margin-top:10px;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="service_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="btn btn-ghost" type="submit" data-confirm="<?php echo $inUse ? 'This service is already in use and will be deactivated. Continue?' : 'Delete this service? This cannot be undone.'; ?>"><?php echo $inUse ? 'Deactivate Service' : 'Delete Service'; ?></button>
                    <?php if ($inUse): ?><div class="muted" style="margin-top:6px;">Service is in transactions, so it can only be deactivated.</div><?php endif; ?>
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
