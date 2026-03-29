<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Bookings';
$moduleKey = 'bookings';
$modulePermission = 'bookings.view';
$moduleDescription = 'Create and manage end-to-end event bookings with lifecycle status tracking.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $customers = [];
    $rows = [];
    $items = [];
    $services = [];

    $q = get_str('q');
    $status = get_str('status');
    $dateFrom = get_str('date_from');
    $dateTo = get_str('date_to');

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $c = $mysqli->prepare('SELECT id, full_name FROM customers WHERE tenant_id = ? ORDER BY full_name LIMIT 200');
        $c->bind_param('i', $tenantId);
        $c->execute();
        $customers = $c->get_result()->fetch_all(MYSQLI_ASSOC);
        $c->close();

        $itemStmt = $mysqli->prepare('SELECT id, item_name, quantity_in_store, quantity_hired_out FROM items WHERE tenant_id = ? ORDER BY item_name');
        $itemStmt->bind_param('i', $tenantId);
        $itemStmt->execute();
        $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemStmt->close();

        $serviceStmt = $mysqli->prepare('SELECT id, service_name, price FROM services WHERE tenant_id = ? ORDER BY service_name');
        $serviceStmt->bind_param('i', $tenantId);
        $serviceStmt->execute();
        $services = $serviceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $serviceStmt->close();

        $sql = 'SELECT b.id, b.booking_ref, b.customer_id, b.event_date, b.status, b.event_type, b.notes, c.full_name AS customer_name, b.event_location,
                EXISTS(SELECT 1 FROM invoices i WHERE i.tenant_id = b.tenant_id AND i.booking_id = b.id) AS has_invoices,
                EXISTS(SELECT 1 FROM returns r WHERE r.tenant_id = b.tenant_id AND r.booking_id = b.id) AS has_returns
                FROM bookings b
                INNER JOIN customers c ON c.id = b.customer_id
                WHERE b.tenant_id = ?';
        $types = 'i';
        $params = [$tenantId];

        if ($q !== '') {
            $sql .= ' AND (b.booking_ref LIKE ? OR c.full_name LIKE ? OR b.event_location LIKE ? OR b.event_type LIKE ?)';
            $like = '%' . $q . '%';
            $types .= 'ssss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (in_array($status, ['draft', 'confirmed', 'in_progress', 'awaiting_return', 'partially_returned', 'completed', 'cancelled'], true)) {
            $sql .= ' AND b.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($dateFrom !== '') {
            $sql .= ' AND b.event_date >= ?';
            $types .= 's';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql .= ' AND b.event_date <= ?';
            $types .= 's';
            $params[] = $dateTo;
        }
        $sql .= ' ORDER BY b.id DESC LIMIT 80';

        $b = $mysqli->prepare($sql);
        if ($b) {
            $bind = [$types];
            foreach ($params as $k => $val) {
                $bind[] = &$params[$k];
            }
            call_user_func_array([$b, 'bind_param'], $bind);
            $b->execute();
            $rows = $b->get_result()->fetch_all(MYSQLI_ASSOC);
            $b->close();
        }
    }
    ?>
    <style>
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; align-items:end; }
        .toolbar .field { margin:0; min-width:160px; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:700px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .action-col { width:70px; }
    </style>

    <section class="card">
        <div class="toolbar">
            <form method="get" action="<?php echo e(app_url('modules/bookings/index.php')); ?>" class="toolbar" style="flex:1;">
                <div class="field"><label>Search</label><input name="q" value="<?php echo e($q); ?>" placeholder="ref, customer, location"></div>
                <div class="field"><label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="awaiting_return" <?php echo $status === 'awaiting_return' ? 'selected' : ''; ?>>Awaiting Return</option>
                        <option value="partially_returned" <?php echo $status === 'partially_returned' ? 'selected' : ''; ?>>Partially Returned</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="field"><label>From</label><input type="date" name="date_from" value="<?php echo e($dateFrom); ?>"></div>
                <div class="field"><label>To</label><input type="date" name="date_to" value="<?php echo e($dateTo); ?>"></div>
                <button class="btn btn-ghost" type="submit">Filter</button>
            </form>
            <button class="btn btn-primary" type="button" data-modal-open="create-booking-modal">+ Booking</button>
            <button class="btn btn-ghost" type="button" data-modal-open="add-booking-item-modal">+ Booking Item</button>
            <button class="btn btn-ghost" type="button" data-modal-open="add-booking-service-modal">+ Booking Service</button>
            <button class="btn btn-ghost" type="button" data-modal-open="add-outsourced-modal">+ Outsourced Item</button>
        </div>

        <table class="table">
            <thead><tr><th>Ref</th><th>Customer</th><th>Date</th><th>Location</th><th>Status</th><th class="action-col">Edit</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo e($row['booking_ref']); ?></td>
                    <td><?php echo e($row['customer_name']); ?></td>
                    <td><?php echo e($row['event_date']); ?></td>
                    <td><?php echo e($row['event_location']); ?></td>
                    <td><?php echo e($row['status']); ?></td>
                    <td><button class="btn btn-ghost" type="button" data-modal-open="edit-booking-<?php echo (int) $row['id']; ?>" title="Edit booking"><i class="fa-solid fa-pencil"></i></button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="6" class="muted">No bookings found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <?php foreach ($rows as $row): ?>
        <?php $hasTransaction = !empty($row['has_invoices']) || !empty($row['has_returns']); ?>
        <div class="modal-backdrop" id="edit-booking-<?php echo (int) $row['id']; ?>">
            <div class="card modal-card">
                <div class="modal-header"><h3 style="margin:0;">Edit Booking: <?php echo e($row['booking_ref']); ?></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                <form method="post" action="<?php echo e(app_url('actions/update_booking.php')); ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="booking_id" value="<?php echo (int) $row['id']; ?>">
                    <div class="field"><label>Customer</label><select name="customer_id" required><?php foreach ($customers as $customer): ?><option value="<?php echo (int) $customer['id']; ?>" <?php echo (int) $row['customer_id'] === (int) $customer['id'] ? 'selected' : ''; ?>><?php echo e($customer['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Event Date</label><input type="date" name="event_date" value="<?php echo e($row['event_date']); ?>" min="<?php echo e(date('Y-m-d')); ?>" required></div>
                    <div class="field"><label>Event Location</label><input name="event_location" value="<?php echo e($row['event_location']); ?>"></div>
                    <div class="field"><label>Event Type</label><input name="event_type" value="<?php echo e($row['event_type']); ?>"></div>
                    <div class="field"><label>Status</label><select name="status"><option value="draft" <?php echo $row['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option><option value="confirmed" <?php echo $row['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option><option value="in_progress" <?php echo $row['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option><option value="awaiting_return" <?php echo $row['status'] === 'awaiting_return' ? 'selected' : ''; ?>>Awaiting Return</option><option value="partially_returned" <?php echo $row['status'] === 'partially_returned' ? 'selected' : ''; ?>>Partially Returned</option><option value="completed" <?php echo $row['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option><option value="cancelled" <?php echo $row['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select></div>
                    <div class="field"><label>Notes</label><textarea name="notes"><?php echo e($row['notes']); ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Update Booking</button>
                </form>

                <form method="post" action="<?php echo e(app_url('actions/delete_booking.php')); ?>" style="margin-top:10px;">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="booking_id" value="<?php echo (int) $row['id']; ?>">
                    <button class="btn btn-ghost" type="submit" <?php echo $hasTransaction ? 'disabled' : ''; ?> data-confirm="Delete this booking? This action cannot be undone.">Delete Booking</button>
                    <?php if ($hasTransaction): ?><div class="muted" style="margin-top:6px;">Cannot delete: booking is already involved in a transaction.</div><?php endif; ?>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="modal-backdrop" id="create-booking-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Create Booking</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_booking.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Customer</label><select name="customer_id" required><?php foreach ($customers as $customer): ?><option value="<?php echo (int) $customer['id']; ?>"><?php echo e($customer['full_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Event Date</label><input type="date" name="event_date" min="<?php echo e(date('Y-m-d')); ?>" required></div>
                <div class="field"><label>Event Location</label><input name="event_location"></div>
                <div class="field"><label>Event Type</label><input name="event_type"></div>
                <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Booking</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="add-booking-item-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add Item To Booking</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/add_booking_item.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Booking</label><select name="booking_id"><?php foreach ($rows as $row): ?><option value="<?php echo (int) $row['id']; ?>"><?php echo e($row['booking_ref']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Item</label><select name="item_id"><?php foreach ($items as $item): ?><option value="<?php echo (int) $item['id']; ?>"><?php echo e($item['item_name']); ?> (Total stock: <?php echo (int) (($item['quantity_in_store'] ?? 0) + ($item['quantity_hired_out'] ?? 0)); ?>)</option><?php endforeach; ?></select></div>
                <div class="field"><label>Quantity</label><input type="number" min="1" name="quantity" value="1"></div>
                <div class="field"><label>Rate</label><input type="number" min="0" step="0.01" name="rate" value="0"></div>
                <button class="btn btn-primary" type="submit">Allocate Item</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="add-booking-service-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add Service To Booking</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/add_booking_service.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Booking</label><select name="booking_id"><?php foreach ($rows as $row): ?><option value="<?php echo (int) $row['id']; ?>"><?php echo e($row['booking_ref']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Service</label><select name="service_id"><?php foreach ($services as $service): ?><option value="<?php echo (int) $service['id']; ?>"><?php echo e($service['service_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Quantity</label><input type="number" min="1" name="quantity" value="1"></div>
                <div class="field"><label>Rate</label><input type="number" min="0" step="0.01" name="rate" value="0"></div>
                <button class="btn btn-primary" type="submit">Attach Service</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="add-outsourced-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Add Outsourced Item</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/add_outsourced_item.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Booking</label><select name="booking_id"><?php foreach ($rows as $row): ?><option value="<?php echo (int) $row['id']; ?>"><?php echo e($row['booking_ref']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Item Name</label><input name="item_name" required></div>
                <div class="field"><label>Provider Name</label><input name="provider_name" required></div>
                <div class="field"><label>Provider Phone</label><input name="provider_phone"></div>
                <div class="field"><label>Source Cost</label><input type="number" min="0" step="0.01" name="source_cost" value="0"></div>
                <div class="field"><label>Quantity</label><input type="number" min="1" name="quantity" value="1"></div>
                <button class="btn btn-primary" type="submit">Save Outsourced Item</button>
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
