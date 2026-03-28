<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Workers';
$moduleKey = 'workers';
$modulePermission = 'bookings.assign';
$moduleDescription = 'Assign workers to bookings and track dispatch/return accountability.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $assignments = [];
    $issues = [];
    $bookings = [];
    $workers = [];
    if ($tenantId > 0 && ($mysqli = db_try())) {
        $b = $mysqli->prepare('SELECT id, booking_ref FROM bookings WHERE tenant_id = ? ORDER BY id DESC LIMIT 100');
        $b->bind_param('i', $tenantId);
        $b->execute();
        $bookings = $b->get_result()->fetch_all(MYSQLI_ASSOC);
        $b->close();

        $w = $mysqli->prepare('SELECT id, full_name FROM tenant_users WHERE tenant_id = ? AND account_status = "active" ORDER BY full_name');
        $w->bind_param('i', $tenantId);
        $w->execute();
        $workers = $w->get_result()->fetch_all(MYSQLI_ASSOC);
        $w->close();

        $a = $mysqli->prepare('SELECT b.booking_ref, u.full_name, bw.dispatch_at, bw.handover_return_at FROM booking_workers bw INNER JOIN bookings b ON b.id = bw.booking_id INNER JOIN tenant_users u ON u.id = bw.worker_user_id WHERE bw.tenant_id = ? ORDER BY bw.id DESC LIMIT 30');
        $a->bind_param('i', $tenantId);
        $a->execute();
        $assignments = $a->get_result()->fetch_all(MYSQLI_ASSOC);
        $a->close();

        $i = $mysqli->prepare('SELECT issue_type, quantity, charge_amount, resolved, created_at FROM worker_accountability WHERE tenant_id = ? ORDER BY id DESC LIMIT 30');
        $i->bind_param('i', $tenantId);
        $i->execute();
        $issues = $i->get_result()->fetch_all(MYSQLI_ASSOC);
        $i->close();
    }
    ?>
    <section class="grid cols-2" style="margin-bottom:14px;">
        <article class="card">
            <h3 style="margin-top:0;">Log Accountability Issue</h3>
            <form method="post" action="<?php echo e(app_url('actions/add_worker_accountability.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Booking</label><select name="booking_id"><?php foreach ($bookings as $booking): ?><option value="<?php echo (int) $booking['id']; ?>"><?php echo e($booking['booking_ref']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Worker</label><select name="worker_user_id"><?php foreach ($workers as $worker): ?><option value="<?php echo (int) $worker['id']; ?>"><?php echo e($worker['full_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Issue Type</label><select name="issue_type"><option value="missing">Missing</option><option value="damaged">Damaged</option><option value="shortage">Shortage</option></select></div>
                <div class="field"><label>Quantity</label><input type="number" min="1" name="quantity" value="1"></div>
                <div class="field"><label>Charge Amount</label><input type="number" step="0.01" min="0" name="charge_amount" value="0"></div>
                <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Issue</button>
            </form>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Worker Assignments</h3>
            <table class="table"><thead><tr><th>Booking</th><th>Worker</th><th>Dispatch</th><th>Handover</th></tr></thead><tbody>
            <?php foreach ($assignments as $row): ?><tr><td><?php echo e($row['booking_ref']); ?></td><td><?php echo e($row['full_name']); ?></td><td><?php echo e($row['dispatch_at']); ?></td><td><?php echo e($row['handover_return_at']); ?></td></tr><?php endforeach; ?>
            <?php if (!$assignments): ?><tr><td colspan="4" class="muted">No worker assignments available.</td></tr><?php endif; ?>
            </tbody></table>
        </article>
    </section>

    <section class="card">
        <h3 style="margin-top:0;">Accountability Issues</h3>
        <table class="table"><thead><tr><th>Issue</th><th>Qty</th><th>Charge</th><th>Resolved</th></tr></thead><tbody>
        <?php foreach ($issues as $row): ?><tr><td><?php echo e($row['issue_type']); ?></td><td><?php echo (int) $row['quantity']; ?></td><td><?php echo number_format((float) $row['charge_amount'], 2); ?></td><td><?php echo (int) $row['resolved'] ? 'Yes' : 'No'; ?></td></tr><?php endforeach; ?>
        <?php if (!$issues): ?><tr><td colspan="4" class="muted">No accountability issues logged.</td></tr><?php endif; ?>
        </tbody></table>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
