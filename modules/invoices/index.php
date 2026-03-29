<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Invoices';
$moduleKey = 'invoices';
$modulePermission = 'invoices.view';
$moduleDescription = 'Issue professional invoices linked to bookings with tax, paid, and balance details.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $customers = [];
    $bookings = [];
    $items = [];
    $services = [];
    $rows = [];

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $c = $mysqli->prepare('SELECT id, full_name FROM customers WHERE tenant_id = ? ORDER BY full_name');
        $c->bind_param('i', $tenantId);
        $c->execute();
        $customers = $c->get_result()->fetch_all(MYSQLI_ASSOC);
        $c->close();

        $b = $mysqli->prepare('SELECT id, booking_ref FROM bookings WHERE tenant_id = ? ORDER BY id DESC LIMIT 200');
        $b->bind_param('i', $tenantId);
        $b->execute();
        $bookings = $b->get_result()->fetch_all(MYSQLI_ASSOC);
        $b->close();

        $it = $mysqli->prepare('SELECT id, item_name FROM items WHERE tenant_id = ? ORDER BY item_name');
        $it->bind_param('i', $tenantId);
        $it->execute();
        $items = $it->get_result()->fetch_all(MYSQLI_ASSOC);
        $it->close();

        $sv = $mysqli->prepare('SELECT id, service_name, price FROM services WHERE tenant_id = ? ORDER BY service_name');
        $sv->bind_param('i', $tenantId);
        $sv->execute();
        $services = $sv->get_result()->fetch_all(MYSQLI_ASSOC);
        $sv->close();

        $i = $mysqli->prepare('SELECT id, invoice_no, issue_date, total_amount, amount_paid, balance_amount, invoice_status FROM invoices WHERE tenant_id = ? ORDER BY id DESC LIMIT 30');
        $i->bind_param('i', $tenantId);
        $i->execute();
        $rows = $i->get_result()->fetch_all(MYSQLI_ASSOC);
        $i->close();
    }
    ?>
    <style>
        .toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; justify-content:space-between; align-items:center; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:680px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .line-item { border:1px solid var(--outline); border-radius:10px; padding:10px; margin-bottom:10px; background:var(--surface-soft); }
        .line-item-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:10px; }
        .line-item-remove { margin-top:8px; }
        .customer-picker-row { display:grid; grid-template-columns: 1fr auto; gap:8px; align-items:end; }
        .customer-picker-row .btn { padding-left:12px; padding-right:12px; }
        .invoices-table-wrap { width:100%; overflow-x:auto; }
        @media (max-width: 760px) {
            .line-item-grid { grid-template-columns: 1fr; }

            .invoices-table thead { display:none; }
            .invoices-table,
            .invoices-table tbody,
            .invoices-table tr,
            .invoices-table td { display:block; width:100%; }

            .invoices-table tr {
                border:1px solid var(--outline);
                border-radius:12px;
                padding:10px;
                margin-bottom:10px;
                background:var(--surface-soft);
            }

            .invoices-table tr.no-invoices-row {
                border:none;
                padding:0;
                margin:0;
                background:transparent;
            }

            .invoices-table td {
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:10px;
                border:none;
                padding:8px 0;
                text-align:right;
            }

            .invoices-table td::before {
                content: attr(data-label);
                font-size:12px;
                color:var(--muted);
                text-transform:uppercase;
                letter-spacing:0.4px;
                text-align:left;
            }

            .invoices-table tr.no-invoices-row td {
                display:block;
                text-align:left;
            }

            .invoices-table tr.no-invoices-row td::before {
                content:'';
            }
        }
    </style>

    <section class="card">
        <div class="toolbar">
            <h3 style="margin:0;">Recent Invoices</h3>
            <button class="btn btn-primary" type="button" data-modal-open="add-invoice-modal">+ Invoice</button>
        </div>

        <div class="invoices-table-wrap">
            <table class="table invoices-table">
                <thead><tr><th>No</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Print</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="No"><?php echo e($row['invoice_no']); ?></td>
                        <td data-label="Total"><?php echo number_format((float) $row['total_amount'], 2); ?></td>
                        <td data-label="Paid"><?php echo number_format((float) $row['amount_paid'], 2); ?></td>
                        <td data-label="Balance"><?php echo number_format((float) $row['balance_amount'], 2); ?></td>
                        <td data-label="Status"><?php echo e($row['invoice_status']); ?></td>
                        <td data-label="Print"><a class="btn btn-ghost" href="<?php echo e(app_url('modules/invoices/print.php?id=' . (int) $row['id'])); ?>" target="_blank">Print</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr class="no-invoices-row"><td colspan="6" class="muted">No invoices yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-backdrop" id="add-invoice-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Create Invoice</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_invoice.php')); ?>" id="invoice-form">
                <?php echo csrf_input(); ?>
                <div class="field">
                    <label>Customer</label>
                    <div class="customer-picker-row">
                        <div>
                            <input id="invoice-customer-search" list="invoice-customer-suggestions" placeholder="Type customer name (e.g. Buffer)">
                            <datalist id="invoice-customer-suggestions">
                                <?php foreach ($customers as $customer): ?><option value="<?php echo e($customer['full_name']); ?>"></option><?php endforeach; ?>
                            </datalist>
                            <select name="customer_id" id="invoice-customer-select" style="margin-top:8px;"><?php foreach ($customers as $customer): ?><option value="<?php echo (int) $customer['id']; ?>"><?php echo e($customer['full_name']); ?></option><?php endforeach; ?></select>
                        </div>
                        <button class="btn btn-ghost" type="button" data-modal-open="add-customer-inline-modal" title="Add customer now">+</button>
                    </div>
                </div>
                <div class="field"><label>Booking (optional)</label><select name="booking_id"><option value="0">- None -</option><?php foreach ($bookings as $booking): ?><option value="<?php echo (int) $booking['id']; ?>"><?php echo e($booking['booking_ref']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Issue Date</label><input type="date" name="issue_date" value="<?php echo e(date('Y-m-d')); ?>"></div>
                <div class="field"><label>Due Date</label><input type="date" name="due_date"></div>

                <h4 style="margin:6px 0 10px;">Invoice Lines (Hired or Bought Items)</h4>
                <div id="invoice-lines"></div>
                <button class="btn btn-ghost" type="button" id="add-invoice-line">+ Add Line</button>

                <div class="field" style="margin-top:10px;"><label>Subtotal</label><input type="number" step="0.01" id="invoice-subtotal" value="0" readonly></div>
                <div class="field"><label>Tax Amount</label><input type="number" step="0.01" name="tax_amount" value="0"></div>
                <button class="btn btn-primary" type="submit">Save Invoice</button>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="add-customer-inline-modal">
        <div class="card modal-card" style="max-width:520px;">
            <div class="modal-header"><h3 style="margin:0;">Add Customer</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form id="add-customer-inline-form">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Full Name</label><input name="full_name" required></div>
                <div class="field"><label>Phone</label><input name="phone" required></div>
                <div class="field"><label>Email</label><input type="email" name="email"></div>
                <div class="field"><label>Address</label><textarea name="address"></textarea></div>
                <div class="field"><label>Notes</label><textarea name="notes"></textarea></div>
                <button class="btn btn-primary" type="submit">Create & Use Customer</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var customerData = <?php echo json_encode($customers); ?> || [];
            var items = <?php echo json_encode($items); ?> || [];
            var services = <?php echo json_encode($services); ?> || [];
            var openButtons = document.querySelectorAll('[data-modal-open]');
            var closeButtons = document.querySelectorAll('[data-modal-close]');
            var customerSelect = document.getElementById('invoice-customer-select');
            var customerSearch = document.getElementById('invoice-customer-search');
            var customerDatalist = document.getElementById('invoice-customer-suggestions');
            var addCustomerInlineForm = document.getElementById('add-customer-inline-form');
            var linesWrap = document.getElementById('invoice-lines');
            var addLineBtn = document.getElementById('add-invoice-line');
            var subtotalInput = document.getElementById('invoice-subtotal');

            function openModal(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.classList.add('open');
                }
            }

            function closeModal(el) {
                var modal = el.closest('.modal-backdrop');
                if (modal) {
                    modal.classList.remove('open');
                }
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
                    if (event.target === this) {
                        this.classList.remove('open');
                    }
                });
            }

            function optionsFrom(arr, valueKey, textKey) {
                var html = '';
                for (var i = 0; i < arr.length; i++) {
                    html += '<option value="' + String(arr[i][valueKey]) + '">' + String(arr[i][textKey]) + '</option>';
                }
                return html;
            }

            function recalcSubtotal() {
                if (!linesWrap || !subtotalInput) {
                    return;
                }
                var rows = linesWrap.querySelectorAll('.line-item');
                var subtotal = 0;
                for (var i = 0; i < rows.length; i++) {
                    var qtyEl = rows[i].querySelector('input[name="quantity[]"]');
                    var rateEl = rows[i].querySelector('input[name="rate[]"]');
                    var qty = qtyEl ? parseFloat(qtyEl.value || '0') : 0;
                    var rate = rateEl ? parseFloat(rateEl.value || '0') : 0;
                    if (qty > 0 && rate >= 0) {
                        subtotal += qty * rate;
                    }
                }
                subtotalInput.value = subtotal.toFixed(2);
            }

            function syncLineVisibility(line) {
                var typeSelect = line.querySelector('select[name="line_type[]"]');
                var itemWrap = line.querySelector('[data-line-item-wrap]');
                var serviceWrap = line.querySelector('[data-line-service-wrap]');
                var customWrap = line.querySelector('[data-line-custom-wrap]');
                var chargeWrap = line.querySelector('[data-line-charge-wrap]');
                var type = typeSelect ? typeSelect.value : 'item';
                if (itemWrap) { itemWrap.style.display = type === 'item' ? '' : 'none'; }
                if (serviceWrap) { serviceWrap.style.display = type === 'service' ? '' : 'none'; }
                if (customWrap) { customWrap.style.display = type === 'custom' ? '' : 'none'; }
                if (chargeWrap) { chargeWrap.style.display = type === 'item' ? '' : 'none'; }
            }

            function addLine() {
                if (!linesWrap) {
                    return;
                }
                var row = document.createElement('div');
                row.className = 'line-item';
                row.innerHTML = '' +
                    '<div class="line-item-grid">' +
                    '<div class="field"><label>Line Type</label><select name="line_type[]"><option value="item">Item</option><option value="service">Service</option><option value="custom">Custom</option></select></div>' +
                    '<div class="field" data-line-charge-wrap><label>Item Charge</label><select name="item_charge_type[]"><option value="hire">Hire</option><option value="buy">Buy</option></select></div>' +
                    '<div class="field" data-line-item-wrap><label>Item</label><select name="item_id[]">' + optionsFrom(items, 'id', 'item_name') + '</select></div>' +
                    '<div class="field" data-line-service-wrap style="display:none;"><label>Service</label><select name="service_id[]">' + optionsFrom(services, 'id', 'service_name') + '</select></div>' +
                    '<div class="field" data-line-custom-wrap style="display:none;"><label>Description</label><input name="line_description[]" placeholder="Custom line description"></div>' +
                    '<div class="field"><label>Quantity</label><input type="number" min="1" step="1" name="quantity[]" value="1"></div>' +
                    '<div class="field"><label>Rate</label><input type="number" min="0" step="0.01" name="rate[]" value="0"></div>' +
                    '</div>' +
                    '<button class="btn btn-ghost line-item-remove" type="button">Remove</button>';
                linesWrap.appendChild(row);

                var typeSelect = row.querySelector('select[name="line_type[]"]');
                if (typeSelect) {
                    typeSelect.addEventListener('change', function () {
                        syncLineVisibility(row);
                    });
                }
                var qtyEl = row.querySelector('input[name="quantity[]"]');
                var rateEl = row.querySelector('input[name="rate[]"]');
                if (qtyEl) { qtyEl.addEventListener('input', recalcSubtotal); }
                if (rateEl) { rateEl.addEventListener('input', recalcSubtotal); }

                var removeBtn = row.querySelector('.line-item-remove');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        row.remove();
                        recalcSubtotal();
                    });
                }

                syncLineVisibility(row);
                recalcSubtotal();
            }

            if (addLineBtn) {
                addLineBtn.addEventListener('click', addLine);
            }

            if (linesWrap && linesWrap.children.length === 0) {
                addLine();
            }

            function refreshCustomerOptions() {
                if (!customerSelect || !customerDatalist) {
                    return;
                }

                customerSelect.innerHTML = '';
                customerDatalist.innerHTML = '';
                for (var i = 0; i < customerData.length; i++) {
                    var row = customerData[i];
                    var option = document.createElement('option');
                    option.value = String(row.id);
                    option.textContent = String(row.full_name || '');
                    customerSelect.appendChild(option);

                    var suggest = document.createElement('option');
                    suggest.value = String(row.full_name || '');
                    customerDatalist.appendChild(suggest);
                }
            }

            function pickCustomerByName(name) {
                if (!customerSelect || !customerData.length) {
                    return;
                }
                var q = String(name || '').trim().toLowerCase();
                if (q === '') {
                    return;
                }

                var picked = null;
                for (var i = 0; i < customerData.length; i++) {
                    var c = customerData[i];
                    var n = String(c.full_name || '').toLowerCase();
                    if (n === q) {
                        picked = c;
                        break;
                    }
                    if (!picked && n.indexOf(q) !== -1) {
                        picked = c;
                    }
                }

                if (picked) {
                    customerSelect.value = String(picked.id);
                }
            }

            if (customerSelect && customerData.length) {
                customerSelect.value = String(customerData[0].id);
                if (customerSearch) {
                    customerSearch.value = String(customerData[0].full_name || '');
                }
            }

            if (customerSearch) {
                customerSearch.addEventListener('input', function () {
                    pickCustomerByName(customerSearch.value);
                });
                customerSearch.addEventListener('change', function () {
                    pickCustomerByName(customerSearch.value);
                });
            }

            if (customerSelect && customerSearch) {
                customerSelect.addEventListener('change', function () {
                    var selected = customerData.find(function (c) { return String(c.id) === String(customerSelect.value); });
                    if (selected) {
                        customerSearch.value = String(selected.full_name || '');
                    }
                });
            }

            if (addCustomerInlineForm) {
                addCustomerInlineForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var formData = new FormData(addCustomerInlineForm);

                    fetch('<?php echo e(app_url('actions/save_customer_inline.php')); ?>', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function (res) { return res.json(); })
                    .then(function (payload) {
                        if (!payload || !payload.success) {
                            alert(payload && payload.message ? payload.message : 'Could not create customer.');
                            return;
                        }

                        var customer = payload.customer || {};
                        customerData.unshift({ id: customer.id, full_name: customer.full_name });
                        refreshCustomerOptions();
                        if (customerSelect) {
                            customerSelect.value = String(customer.id);
                        }
                        if (customerSearch) {
                            customerSearch.value = String(customer.full_name || '');
                        }

                        var modal = document.getElementById('add-customer-inline-modal');
                        if (modal) {
                            modal.classList.remove('open');
                        }
                        addCustomerInlineForm.reset();
                    })
                    .catch(function () {
                        alert('Could not create customer right now.');
                    });
                });
            }
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
