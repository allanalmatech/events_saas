<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Support Tickets';
$moduleKey = 'tickets';
$modulePermission = 'tickets.view';
$moduleDescription = 'Create, monitor, and resolve support issues with threaded communication.';

$contentRenderer = function (): void {
    $rows = [];
    $ticketChoices = [];
    $tenantId = current_tenant_id();
    if ($mysqli = db_try()) {
        if (auth_role() === 'director') {
            $q = $mysqli->query('SELECT st.id, t.business_name, st.subject, st.priority, st.ticket_status, st.updated_at FROM support_tickets st INNER JOIN tenants t ON t.id = st.tenant_id ORDER BY st.id DESC LIMIT 40');
            $rows = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];
            $choiceQ = $mysqli->query('SELECT st.id, st.subject, t.business_name FROM support_tickets st INNER JOIN tenants t ON t.id = st.tenant_id ORDER BY st.id DESC LIMIT 120');
            $ticketChoices = $choiceQ ? $choiceQ->fetch_all(MYSQLI_ASSOC) : [];
        } elseif ($tenantId) {
            $stmt = $mysqli->prepare('SELECT id, subject, priority, ticket_status, updated_at FROM support_tickets WHERE tenant_id = ? ORDER BY id DESC LIMIT 40');
            $tid = (int) $tenantId;
            $stmt->bind_param('i', $tid);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $choiceStmt = $mysqli->prepare('SELECT id, subject FROM support_tickets WHERE tenant_id = ? ORDER BY id DESC LIMIT 120');
            $choiceStmt->bind_param('i', $tid);
            $choiceStmt->execute();
            $ticketChoices = $choiceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $choiceStmt->close();
        }
    }
    ?>
    <style>
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
        .toolbar-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:760px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .smart-form { display:block; }
        .smart-form .field { margin-bottom:12px; }
        .smart-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .smart-note { font-size:12px; color:var(--muted); margin:-4px 0 10px; }
        @media (max-width: 760px) { .smart-grid { grid-template-columns:1fr; } }
    </style>

    <section class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Ticket List</h3>
            <div class="toolbar-actions">
                <button class="btn btn-primary" type="button" data-modal-open="ticket-create-modal">Open Ticket</button>
                <button class="btn btn-ghost" type="button" data-modal-open="ticket-reply-modal">Reply To Ticket</button>
            </div>
        </div>
        <table class="table">
            <thead><tr><?php if (auth_role() === 'director'): ?><th>Tenant</th><?php endif; ?><th>Subject</th><th>Priority</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php if (auth_role() === 'director'): ?><td><?php echo e($row['business_name']); ?></td><?php endif; ?>
                    <td><?php echo e($row['subject']); ?></td>
                    <td><?php echo e($row['priority']); ?></td>
                    <td><?php echo e($row['ticket_status']); ?></td>
                    <td><?php echo e($row['updated_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="muted">No tickets found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <div class="modal-backdrop" id="ticket-create-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Open Ticket</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <p class="smart-note">Create a support issue with priority and clear summary.</p>
            <form method="post" action="<?php echo e(app_url('actions/create_ticket.php')); ?>" class="smart-form">
                <?php echo csrf_input(); ?>
                <div class="smart-grid">
                    <div class="field"><label>Category</label><input name="category" value="general"></div>
                    <div class="field"><label>Priority</label><select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                </div>
                <div class="field"><label>Subject</label><input name="subject" required></div>
                <div class="field"><label>Message</label><textarea name="message" required></textarea></div>
                <button class="btn btn-primary" type="submit">Create Ticket</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="ticket-reply-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Reply To Ticket</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <p class="smart-note">Choose ticket by subject to avoid ID mix-ups.</p>
            <form method="post" action="<?php echo e(app_url('actions/reply_ticket.php')); ?>" class="smart-form">
                <?php echo csrf_input(); ?>
                <div class="field">
                    <label>Ticket</label>
                    <select name="ticket_id" required>
                        <?php foreach ($ticketChoices as $ticket): ?>
                            <option value="<?php echo (int) $ticket['id']; ?>">#<?php echo (int) $ticket['id']; ?> - <?php echo e($ticket['subject']); ?><?php echo isset($ticket['business_name']) ? ' (' . e($ticket['business_name']) . ')' : ''; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Reply</label><textarea name="message" required></textarea></div>
                <button class="btn btn-primary" type="submit">Post Reply</button>
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
