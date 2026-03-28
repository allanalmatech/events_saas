<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Returns';
$moduleKey = 'returns';
$modulePermission = 'returns.view';
$moduleDescription = 'Process store returns, missing/damaged declarations, and stock reconciliation.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $bookings = [];
    $rows = [];
    $bookingItems = [];

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $b = $mysqli->prepare('SELECT id, booking_ref FROM bookings WHERE tenant_id = ? ORDER BY id DESC LIMIT 100');
        $b->bind_param('i', $tenantId);
        $b->execute();
        $bookings = $b->get_result()->fetch_all(MYSQLI_ASSOC);
        $b->close();

        $r = $mysqli->prepare('SELECT r.id, b.booking_ref, r.return_status, r.processed_at FROM returns r INNER JOIN bookings b ON b.id = r.booking_id WHERE r.tenant_id = ? ORDER BY r.id DESC LIMIT 25');
        $r->bind_param('i', $tenantId);
        $r->execute();
        $rows = $r->get_result()->fetch_all(MYSQLI_ASSOC);
        $r->close();

        $bi = $mysqli->prepare('SELECT bi.id, b.booking_ref, i.item_name, bi.quantity FROM booking_items bi INNER JOIN bookings b ON b.id = bi.booking_id INNER JOIN items i ON i.id = bi.item_id WHERE bi.tenant_id = ? ORDER BY bi.id DESC LIMIT 100');
        $bi->bind_param('i', $tenantId);
        $bi->execute();
        $bookingItems = $bi->get_result()->fetch_all(MYSQLI_ASSOC);
        $bi->close();
    }
    ?>
    <section class="grid cols-2">
        <article class="card">
            <h3 style="margin-top:0;">Process Return</h3>
            <form method="post" action="<?php echo e(app_url('actions/process_return.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Booking</label><select name="booking_id"><?php foreach ($bookings as $booking): ?><option value="<?php echo (int) $booking['id']; ?>"><?php echo e($booking['booking_ref']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Booking Item</label><select name="booking_item_id"><option value="0">- Optional -</option><?php foreach ($bookingItems as $line): ?><option value="<?php echo (int) $line['id']; ?>"><?php echo e($line['booking_ref'] . ' - ' . $line['item_name'] . ' (sent ' . $line['quantity'] . ')'); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Return Status</label><select name="return_status"><option value="pending">Pending</option><option value="partial">Partial</option><option value="full">Full</option><option value="damaged">Damaged</option><option value="lost">Lost</option></select></div>
                <div class="field"><label>Qty Sent</label><input type="number" min="0" name="qty_sent" value="0"></div>
                <div class="field"><label>Qty Returned</label><input type="number" min="0" name="qty_returned" value="0"></div>
                <div class="field"><label>Qty Missing</label><input type="number" min="0" name="qty_missing" value="0"></div>
                <div class="field"><label>Qty Damaged</label><input type="number" min="0" name="qty_damaged" value="0"></div>
                <div class="field"><label>Return To Owner</label><select name="mark_return_to_owner"><option value="0">No</option><option value="1">Yes</option></select></div>
                <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Return</button>
            </form>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Recent Returns</h3>
            <table class="table">
                <thead><tr><th>Booking</th><th>Status</th><th>Processed At</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr><td><?php echo e($row['booking_ref']); ?></td><td><?php echo e($row['return_status']); ?></td><td><?php echo e($row['processed_at']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="3" class="muted">No returns captured.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
