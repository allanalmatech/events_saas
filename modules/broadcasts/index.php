<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Broadcasts';
$moduleKey = 'broadcasts';
$modulePermission = 'broadcasts.view';
$moduleDescription = 'Send and track broadcast announcements within tenant or at platform level.';

$contentRenderer = function (): void {
    $tenantId = current_tenant_id();
    $rows = [];
    if ($mysqli = db_try()) {
        if (auth_role() === 'director') {
            $q = $mysqli->query('SELECT sender_type, subject, audience_type, created_at FROM broadcasts ORDER BY id DESC LIMIT 40');
            $rows = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];
        } elseif ($tenantId) {
            $stmt = $mysqli->prepare('SELECT sender_type, subject, audience_type, created_at FROM broadcasts WHERE tenant_id = ? ORDER BY id DESC LIMIT 40');
            $tid = (int) $tenantId;
            $stmt->bind_param('i', $tid);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
    ?>
    <section class="grid cols-2">
        <article class="card">
            <h3 style="margin-top:0;">Send Broadcast</h3>
            <form method="post" action="<?php echo e(app_url('actions/send_broadcast.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Subject</label><input name="subject" required></div>
                <div class="field"><label>Message</label><textarea name="message" required></textarea></div>
                <button class="btn btn-primary" type="submit">Broadcast</button>
            </form>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">History</h3>
            <table class="table">
                <thead><tr><th>Sender</th><th>Subject</th><th>Audience</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr><td><?php echo e($row['sender_type']); ?></td><td><?php echo e($row['subject']); ?></td><td><?php echo e($row['audience_type']); ?></td><td><?php echo e($row['created_at']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="muted">No broadcasts yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
