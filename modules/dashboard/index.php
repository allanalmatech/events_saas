<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Dashboard';
$moduleKey = 'dashboard';
$modulePermission = 'dashboard.view';
$moduleDescription = 'Operational snapshot across tenancy, bookings, invoices, and subscription status.';

$contentRenderer = function (): void {
    $mysqli = db_try();
    $tenantId = current_tenant_id();
    $isDirector = auth_role() === 'director';
    $unreadTickets = 0;
    $ticketAlerts = [];

    $cards = [
        'tenants' => 0,
        'bookings' => 0,
        'invoices' => 0,
        'customers' => 0,
    ];

    if ($mysqli) {
        if ($isDirector) {
            $cards['tenants'] = (int) (($mysqli->query('SELECT COUNT(*) c FROM tenants')->fetch_assoc()['c']) ?? 0);
            $cards['bookings'] = (int) (($mysqli->query('SELECT COUNT(*) c FROM bookings')->fetch_assoc()['c']) ?? 0);
            $cards['invoices'] = (int) (($mysqli->query('SELECT COUNT(*) c FROM invoices')->fetch_assoc()['c']) ?? 0);
            $cards['customers'] = (int) (($mysqli->query('SELECT COUNT(*) c FROM customers')->fetch_assoc()['c']) ?? 0);

            $unreadQ = $mysqli->query('SELECT COUNT(*) c FROM support_tickets WHERE ticket_status IN ("open", "in_progress")');
            $unreadTickets = (int) (($unreadQ ? $unreadQ->fetch_assoc()['c'] : 0) ?? 0);
            $alertsQ = $mysqli->query('SELECT st.id, t.business_name, st.subject, st.priority, st.updated_at FROM support_tickets st INNER JOIN tenants t ON t.id = st.tenant_id WHERE st.ticket_status IN ("open", "in_progress") ORDER BY st.updated_at DESC LIMIT 5');
            $ticketAlerts = $alertsQ ? $alertsQ->fetch_all(MYSQLI_ASSOC) : [];
        } elseif ($tenantId) {
            $stmt = $mysqli->prepare('SELECT COUNT(*) c FROM bookings WHERE tenant_id = ?');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $cards['bookings'] = (int) (($stmt->get_result()->fetch_assoc()['c']) ?? 0);
            $stmt->close();

            $stmt = $mysqli->prepare('SELECT COUNT(*) c FROM invoices WHERE tenant_id = ?');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $cards['invoices'] = (int) (($stmt->get_result()->fetch_assoc()['c']) ?? 0);
            $stmt->close();

            $stmt = $mysqli->prepare('SELECT COUNT(*) c FROM customers WHERE tenant_id = ?');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $cards['customers'] = (int) (($stmt->get_result()->fetch_assoc()['c']) ?? 0);
            $stmt->close();

            $summary = tenant_billing_summary($tenantId);
            $cards['tenants'] = $summary['overdue'];

            $stmt = $mysqli->prepare('SELECT COUNT(*) c FROM support_tickets WHERE tenant_id = ? AND ticket_status IN ("open", "in_progress")');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $unreadTickets = (int) (($stmt->get_result()->fetch_assoc()['c']) ?? 0);
            $stmt->close();

            $stmt = $mysqli->prepare('SELECT id, subject, priority, updated_at FROM support_tickets WHERE tenant_id = ? AND ticket_status IN ("open", "in_progress") ORDER BY updated_at DESC LIMIT 5');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $ticketAlerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    ?>
    <section class="grid cols-4" style="margin-bottom:14px;">
        <article class="card"><div class="muted"><?php echo auth_role() === 'director' ? 'Total Tenants' : 'Overdue Billing Invoices'; ?></div><div class="kpi"><?php echo (int) $cards['tenants']; ?></div></article>
        <article class="card"><div class="muted">Bookings</div><div class="kpi"><?php echo (int) $cards['bookings']; ?></div></article>
        <article class="card"><div class="muted">Invoices</div><div class="kpi"><?php echo (int) $cards['invoices']; ?></div></article>
        <article class="card"><div class="muted">Customers</div><div class="kpi"><?php echo (int) $cards['customers']; ?></div></article>
    </section>

    <section class="grid cols-2">
        <article class="card">
            <h3 style="margin-top:0;">Quick Actions</h3>
            <p class="muted">Use these shortcuts to run daily operations quickly.</p>
            <?php if ($isDirector): ?>
                <a class="btn btn-primary" href="<?php echo e(app_url('modules/billing/index.php')); ?>">Create Billing Invoice</a>
                <a class="btn btn-ghost" href="<?php echo e(app_url('modules/billing/index.php')); ?>">Record Billing Payment</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?php echo e(app_url('modules/bookings/index.php')); ?>">+ New Booking</a>
                <a class="btn btn-ghost" href="<?php echo e(app_url('modules/invoices/index.php')); ?>">+ New Invoice</a>
            <?php endif; ?>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Unread Ticket Alerts (<?php echo (int) $unreadTickets; ?>)</h3>
            <?php if ($ticketAlerts): ?>
                <ul class="muted" style="margin-bottom:0;">
                    <?php foreach ($ticketAlerts as $ticket): ?>
                        <li>
                            <?php if ($isDirector): ?><?php echo e($ticket['business_name'] ?? 'Tenant'); ?> - <?php endif; ?>
                            #<?php echo (int) $ticket['id']; ?> <?php echo e($ticket['subject']); ?> (<?php echo e($ticket['priority']); ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div style="margin-top:8px;"><a class="btn btn-ghost" href="<?php echo e(app_url('modules/tickets/index.php')); ?>">Open Tickets</a></div>
            <?php else: ?>
                <p class="muted" style="margin:0;">No unread ticket alerts right now.</p>
            <?php endif; ?>
        </article>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
