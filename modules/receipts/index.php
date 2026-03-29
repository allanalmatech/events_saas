<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Receipts';
$moduleKey = 'receipts';
$modulePermission = 'receipts.view';
$moduleDescription = 'Generate receipts for full or partial payments and maintain running balance visibility.';

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

        $r = $mysqli->prepare('SELECT id, receipt_no, receipt_date, amount_paid, payment_method, balance_after FROM receipts WHERE tenant_id = ? ORDER BY id DESC LIMIT 30');
        $r->bind_param('i', $tenantId);
        $r->execute();
        $rows = $r->get_result()->fetch_all(MYSQLI_ASSOC);
        $r->close();
    }
    ?>
    <style>
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; justify-content:space-between; align-items:center; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:680px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .receipts-table-wrap { width:100%; overflow-x:auto; }

        @media (max-width: 760px) {
            .receipts-table thead { display:none; }
            .receipts-table,
            .receipts-table tbody,
            .receipts-table tr,
            .receipts-table td { display:block; width:100%; }

            .receipts-table tr {
                border:1px solid var(--outline);
                border-radius:12px;
                padding:10px;
                margin-bottom:10px;
                background:var(--surface-soft);
            }

            .receipts-table tr.no-receipts-row {
                border:none;
                padding:0;
                margin:0;
                background:transparent;
            }

            .receipts-table td {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:10px;
                border:none;
                padding:8px 0;
                text-align:right;
            }

            .receipts-table td::before {
                content: attr(data-label);
                font-size:12px;
                color:var(--muted);
                text-transform:uppercase;
                letter-spacing:0.4px;
                text-align:left;
            }

            .receipts-table tr.no-receipts-row td {
                display:block;
                text-align:left;
            }

            .receipts-table tr.no-receipts-row td::before {
                content:'';
            }
        }
    </style>

    <section class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Recent Receipts</h3>
            <button class="btn btn-primary" type="button" data-modal-open="add-receipt-modal">+ Receipt</button>
        </div>
        <div class="receipts-table-wrap">
            <table class="table receipts-table">
                <thead><tr><th>No</th><th>Date</th><th>Amount</th><th>Method</th><th>Balance After</th><th>Print</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="No"><?php echo e($row['receipt_no']); ?></td>
                        <td data-label="Date"><?php echo e($row['receipt_date']); ?></td>
                        <td data-label="Amount"><?php echo number_format((float) $row['amount_paid'], 2); ?></td>
                        <td data-label="Method"><?php echo e($row['payment_method']); ?></td>
                        <td data-label="Balance After"><?php echo number_format((float) $row['balance_after'], 2); ?></td>
                        <td data-label="Print"><a class="btn btn-ghost" href="<?php echo e(app_url('modules/receipts/print.php?id=' . (int) $row['id'])); ?>" target="_blank">Print</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr class="no-receipts-row"><td colspan="6" class="muted">No receipts available.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-backdrop" id="add-receipt-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Generate Receipt</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/generate_receipt.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Invoice</label><select name="invoice_id"><?php foreach ($invoices as $invoice): ?><option value="<?php echo (int) $invoice['id']; ?>"><?php echo e($invoice['invoice_no']); ?> (Bal: <?php echo number_format((float) $invoice['balance_amount'], 2); ?>)</option><?php endforeach; ?></select></div>
                <div class="field"><label>Amount Paid</label><input type="number" step="0.01" min="0.01" name="amount_paid" required></div>
                <div class="field"><label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="card">Card</option>
                        <option value="cheque">Cheque</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="field"><label>Reference</label><input name="payment_reference"></div>
                <button class="btn btn-primary" type="submit">Generate</button>
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
