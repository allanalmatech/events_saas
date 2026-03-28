<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Notifications';
$moduleKey = 'notifications';
$modulePermission = 'notifications.view';
$moduleDescription = 'In-app alerts and reminder tracking for operational and billing events.';

$contentRenderer = function (): void {
    $tenantId = current_tenant_id();
    $user = auth_user();
    $rows = [];

    if ($mysqli = db_try()) {
        if (auth_role() === 'director') {
            $q = $mysqli->query('SELECT title, message, channel_key, created_at, is_read FROM notifications ORDER BY id DESC LIMIT 40');
            $rows = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];
        } else {
            $stmt = $mysqli->prepare('SELECT title, message, channel_key, created_at, is_read FROM notifications WHERE tenant_id = ? AND (user_id IS NULL OR user_id = ?) ORDER BY id DESC LIMIT 40');
            $tid = (int) $tenantId;
            $uid = (int) $user['id'];
            $stmt->bind_param('ii', $tid, $uid);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    ?>
    <section class="card">
        <table class="table">
            <thead><tr><th>Title</th><th>Message</th><th>Channel</th><th>Date</th><th>Read</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr><td><?php echo e($row['title']); ?></td><td><?php echo e($row['message']); ?></td><td><?php echo e($row['channel_key']); ?></td><td><?php echo e($row['created_at']); ?></td><td><?php echo (int) $row['is_read'] ? 'Yes' : 'No'; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="muted">No notifications currently.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
