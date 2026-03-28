<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Inventory Items';
$moduleKey = 'items';
$modulePermission = 'items.view';
$moduleDescription = 'Manage hirable stock, availability, damaged/lost counts, and sourcing origin.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $rows = [];
    $providers = [];

    $q = get_str('q');
    $owner = get_str('owner');
    $status = get_str('status');
    $providerQ = get_str('provider_q');

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $itemSql = 'SELECT id, item_name, sku, unit_type, quantity_total, quantity_in_store, quantity_hired_out, owner_type, status FROM items WHERE tenant_id = ?';
        $itemTypes = 'i';
        $itemParams = [$tenantId];

        if ($q !== '') {
            $itemSql .= ' AND (item_name LIKE ? OR sku LIKE ?)';
            $like = '%' . $q . '%';
            $itemTypes .= 'ss';
            $itemParams[] = $like;
            $itemParams[] = $like;
        }
        if (in_array($owner, ['owned', 'external'], true)) {
            $itemSql .= ' AND owner_type = ?';
            $itemTypes .= 's';
            $itemParams[] = $owner;
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $itemSql .= ' AND status = ?';
            $itemTypes .= 's';
            $itemParams[] = $status;
        }
        $itemSql .= ' ORDER BY id DESC LIMIT 60';

        $stmt = $mysqli->prepare($itemSql);
        if ($stmt) {
            $bind = [$itemTypes];
            foreach ($itemParams as $k => $val) {
                $bind[] = &$itemParams[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        $providerSql = 'SELECT id, provider_name, contact_person, phone, email, status FROM external_providers WHERE tenant_id = ?';
        $providerTypes = 'i';
        $providerParams = [$tenantId];
        if ($providerQ !== '') {
            $providerSql .= ' AND (provider_name LIKE ? OR contact_person LIKE ? OR phone LIKE ?)';
            $providerLike = '%' . $providerQ . '%';
            $providerTypes .= 'sss';
            $providerParams[] = $providerLike;
            $providerParams[] = $providerLike;
            $providerParams[] = $providerLike;
        }
        $providerSql .= ' ORDER BY id DESC LIMIT 40';

        $p = $mysqli->prepare($providerSql);
        if ($p) {
            $bind = [$providerTypes];
            foreach ($providerParams as $k => $val) {
                $bind[] = &$providerParams[$k];
            }
            call_user_func_array([$p, 'bind_param'], $bind);
            $p->execute();
            $providers = $p->get_result()->fetch_all(MYSQLI_ASSOC);
            $p->close();
        }
    }
    ?>
    <style>
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; align-items:end; }
        .toolbar .field { margin:0; min-width:160px; }
        .actions-inline { display:flex; gap:6px; flex-wrap:wrap; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:680px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    </style>

    <section class="card" style="margin-bottom:14px;">
        <div class="toolbar">
            <form method="get" action="<?php echo e(app_url('modules/items/index.php')); ?>" class="toolbar" style="flex:1;">
                <div class="field"><label>Search Items</label><input name="q" value="<?php echo e($q); ?>" placeholder="name or sku"></div>
                <div class="field"><label>Owner</label><select name="owner"><option value="">All</option><option value="owned" <?php echo $owner === 'owned' ? 'selected' : ''; ?>>Owned</option><option value="external" <?php echo $owner === 'external' ? 'selected' : ''; ?>>External</option></select></div>
                <div class="field"><label>Status</label><select name="status"><option value="">All</option><option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                <button class="btn btn-ghost" type="submit">Filter</button>
            </form>
            <button class="btn btn-primary" type="button" data-modal-open="add-item-modal">+ Item</button>
        </div>
        <table class="table">
            <thead><tr><th>Item</th><th>SKU</th><th>Unit</th><th>Total</th><th>In Store</th><th>Hired</th><th>Owner</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo e($row['item_name']); ?></td>
                    <td><?php echo e($row['sku']); ?></td>
                    <td><?php echo e($row['unit_type']); ?></td>
                    <td><?php echo (int) $row['quantity_total']; ?></td>
                    <td><?php echo (int) $row['quantity_in_store']; ?></td>
                    <td><?php echo (int) $row['quantity_hired_out']; ?></td>
                    <td><?php echo e($row['owner_type']); ?></td>
                    <td><?php echo e($row['status']); ?></td>
                    <td><button class="btn btn-ghost" type="button" data-modal-open="edit-item-<?php echo (int) $row['id']; ?>">Edit</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9" class="muted">No items found for the selected filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <div class="toolbar">
            <form method="get" action="<?php echo e(app_url('modules/items/index.php')); ?>" class="toolbar" style="flex:1;">
                <div class="field"><label>Search Providers</label><input name="provider_q" value="<?php echo e($providerQ); ?>" placeholder="name, contact, phone"></div>
                <button class="btn btn-ghost" type="submit">Filter</button>
            </form>
            <button class="btn btn-primary" type="button" data-modal-open="add-provider-modal">+ Provider</button>
        </div>
        <table class="table">
            <thead><tr><th>Provider</th><th>Contact</th><th>Phone</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($providers as $provider): ?>
                <tr>
                    <td><?php echo e($provider['provider_name']); ?></td>
                    <td><?php echo e($provider['contact_person']); ?></td>
                    <td><?php echo e($provider['phone']); ?></td>
                    <td><?php echo e($provider['email']); ?></td>
                    <td><?php echo e($provider['status']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$providers): ?><tr><td colspan="5" class="muted">No providers found for the selected filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <div class="modal-backdrop" id="add-item-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add Inventory Item</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_item.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Item Name</label><input name="item_name" required></div>
                <div class="field"><label>SKU (optional)</label><input name="sku" placeholder="Leave blank to auto-generate"></div>
                <div class="field"><label>Unit Type</label><input name="unit_type" placeholder="pcs, sets, tents"></div>
                <div class="field"><label>Quantity Total</label><input name="quantity_total" type="number" min="0" value="0"></div>
                <div class="field"><label>Owner Type</label><select name="owner_type"><option value="owned">Owned</option><option value="external">External</option></select></div>
                <button class="btn btn-primary" type="submit">Save Item</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="add-provider-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add External Provider</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_external_provider.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Provider Name</label><input name="provider_name" required></div>
                <div class="field"><label>Contact Person</label><input name="contact_person"></div>
                <div class="field"><label>Phone</label><input name="phone"></div>
                <div class="field"><label>Email</label><input type="email" name="email"></div>
                <div class="field"><label>Address</label><input name="address"></div>
                <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Provider</button>
            </form>
        </div>
    </div>

    <?php foreach ($rows as $row): ?>
        <div class="modal-backdrop" id="edit-item-<?php echo (int) $row['id']; ?>">
            <div class="card modal-card">
                <div class="modal-header"><h3 style="margin:0;">Edit Item: <?php echo e($row['item_name']); ?></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                <form method="post" action="<?php echo e(app_url('actions/update_item.php')); ?>" style="margin-bottom:10px;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="item_id" value="<?php echo (int) $row['id']; ?>">
                    <div class="field"><label>Item Name</label><input name="item_name" value="<?php echo e($row['item_name']); ?>" required></div>
                    <div class="field"><label>SKU</label><input name="sku" value="<?php echo e($row['sku']); ?>"></div>
                    <div class="field"><label>Unit Type</label><input name="unit_type" value="<?php echo e($row['unit_type']); ?>"></div>
                    <div class="field"><label>Quantity Total</label><input name="quantity_total" type="number" min="0" value="<?php echo (int) $row['quantity_total']; ?>"></div>
                    <div class="field"><label>Owner Type</label><select name="owner_type"><option value="owned" <?php echo $row['owner_type'] === 'owned' ? 'selected' : ''; ?>>Owned</option><option value="external" <?php echo $row['owner_type'] === 'external' ? 'selected' : ''; ?>>External</option></select></div>
                    <div class="field"><label>Status</label><select name="status"><option value="active" <?php echo $row['status'] === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $row['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option></select></div>
                    <div class="actions-inline">
                        <button class="btn btn-primary" type="submit">Update Item</button>
                    </div>
                </form>

                <form method="post" action="<?php echo e(app_url('actions/delete_item.php')); ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="item_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="btn btn-ghost" type="submit" data-confirm="Delete this item? This only works when no transactions are linked.">Delete Item</button>
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
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
