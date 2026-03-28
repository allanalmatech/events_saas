<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Calendar';
$moduleKey = 'calendar';
$modulePermission = 'calendar.view';
$moduleDescription = 'Monthly overview for events, return deadlines, and payment reminders.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $events = [];

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $stmt = $mysqli->prepare('SELECT booking_ref, event_date, status FROM bookings WHERE tenant_id = ? AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 30');
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    ?>
    <section class="card">
        <h3 style="margin-top:0;">Upcoming Events</h3>
        <table class="table">
            <thead><tr><th>Booking Ref</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($events as $event): ?>
                <tr><td><?php echo e($event['booking_ref']); ?></td><td><?php echo e($event['event_date']); ?></td><td><?php echo e($event['status']); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$events): ?><tr><td colspan="3" class="muted">No upcoming events.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
