<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Payments';
$moduleKey = 'payments';
$modulePermission = 'payments.view';
$moduleDescription = 'Capture payment entries, references, methods, and timelines against invoices.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $invoices = [];
    $rows = [];

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $i = $mysqli->prepare('SELECT id, invoice_no, balance_amount FROM invoices WHERE tenant_id = ? ORDER BY id DESC LIMIT 200');
        $i->bind_param('i', $tenantId);
        $i->execute();
        $invoices = $i->get_result()->fetch_all(MYSQLI_ASSOC);
        $i->close();

        $p = $mysqli->prepare('SELECT p.payment_date, i.invoice_no, p.amount, p.payment_method, p.payment_reference FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? ORDER BY p.id DESC LIMIT 30');
        $p->bind_param('i', $tenantId);
        $p->execute();
        $rows = $p->get_result()->fetch_all(MYSQLI_ASSOC);
        $p->close();
    }
    ?>
    <style>
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:620px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    </style>

    <section class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Recent Payments</h3>
            <button class="btn btn-primary" type="button" data-modal-open="record-payment-modal">+ Record Payment</button>
        </div>

        <table class="table">
            <thead><tr><th>Date</th><th>Invoice</th><th>Amount</th><th>Method</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo e($row['payment_date']); ?></td>
                    <td><?php echo e($row['invoice_no']); ?></td>
                    <td><?php echo number_format((float) $row['amount'], 2); ?></td>
                    <td><?php echo e($row['payment_method']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="4" class="muted">No payments posted.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <div class="modal-backdrop" id="record-payment-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Record Payment</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_payment.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Invoice</label><select name="invoice_id"><?php foreach ($invoices as $invoice): ?><option value="<?php echo (int) $invoice['id']; ?>"><?php echo e($invoice['invoice_no']); ?> (Bal: <?php echo number_format((float) $invoice['balance_amount'], 2); ?>)</option><?php endforeach; ?></select></div>
                <div class="field"><label>Amount</label><input name="amount" type="number" step="0.01" min="0.01" required></div>
                <div class="field"><label>Date</label><input name="payment_date" type="date" value="<?php echo e(date('Y-m-d')); ?>"></div>
                <div class="field"><label>Method</label><input name="payment_method" placeholder="Cash, Mobile Money, Bank"></div>
                <div class="field"><label>Reference</label><input name="payment_reference"></div>
                <button class="btn btn-primary" type="submit">Save Payment</button>
            </form>
        </div>
    </div>

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
