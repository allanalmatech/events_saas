<?php
require_once __DIR__ . '/../../includes/functions.php';
require_tenant_user();

$pageTitle = 'Messages';
$moduleKey = 'messages';
$modulePermission = 'messages.view';
$moduleDescription = 'Internal messaging and tenant-to-tenant collaboration conversations.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $tenants = [];
    $rows = [];

    if ($mysqli = db_try()) {
        $all = $mysqli->prepare('SELECT id, business_name FROM tenants WHERE id <> ? ORDER BY business_name');
        $all->bind_param('i', $tenantId);
        $all->execute();
        $tenants = $all->get_result()->fetch_all(MYSQLI_ASSOC);
        $all->close();

        $msg = $mysqli->prepare('SELECT tm.subject, tm.message, tm.created_at, t.business_name AS from_business FROM tenant_messages tm INNER JOIN tenants t ON t.id = tm.from_tenant_id WHERE tm.to_tenant_id = ? ORDER BY tm.id DESC LIMIT 30');
        $msg->bind_param('i', $tenantId);
        $msg->execute();
        $rows = $msg->get_result()->fetch_all(MYSQLI_ASSOC);
        $msg->close();
    }
    ?>
    <section class="grid cols-2">
        <article class="card">
            <h3 style="margin-top:0;">Send Tenant Message</h3>
            <form method="post" action="<?php echo e(app_url('actions/send_message.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>To Tenant</label><select name="to_tenant_id"><?php foreach ($tenants as $tenant): ?><option value="<?php echo (int) $tenant['id']; ?>"><?php echo e($tenant['business_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Subject</label><input name="subject"></div>
                <div class="field"><label>Message</label><textarea name="message" required></textarea></div>
                <button class="btn btn-primary" type="submit">Send</button>
            </form>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Incoming Messages</h3>
            <table class="table">
                <thead><tr><th>From</th><th>Subject</th><th>Message</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr><td><?php echo e($row['from_business']); ?></td><td><?php echo e($row['subject']); ?></td><td><?php echo e($row['message']); ?></td><td><?php echo e($row['created_at']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="muted">No messages.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
