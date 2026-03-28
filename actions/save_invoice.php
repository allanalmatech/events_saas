<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('invoices.create');
enforce_tenant_lock_for_write();

$tenantId = (int) current_tenant_id();
$customerId = (int) post_str('customer_id', '0');
$bookingId = (int) post_str('booking_id', '0');
$issueDate = post_str('issue_date', date('Y-m-d'));
$dueDate = post_str('due_date');
$taxAmount = (float) post_str('tax_amount', '0');

$lineTypes = isset($_POST['line_type']) && is_array($_POST['line_type']) ? $_POST['line_type'] : [];
$lineItemIds = isset($_POST['item_id']) && is_array($_POST['item_id']) ? $_POST['item_id'] : [];
$lineServiceIds = isset($_POST['service_id']) && is_array($_POST['service_id']) ? $_POST['service_id'] : [];
$lineDescriptions = isset($_POST['line_description']) && is_array($_POST['line_description']) ? $_POST['line_description'] : [];
$lineChargeTypes = isset($_POST['item_charge_type']) && is_array($_POST['item_charge_type']) ? $_POST['item_charge_type'] : [];
$lineQuantities = isset($_POST['quantity']) && is_array($_POST['quantity']) ? $_POST['quantity'] : [];
$lineRates = isset($_POST['rate']) && is_array($_POST['rate']) ? $_POST['rate'] : [];

if ($customerId <= 0) {
    flash('error', 'Customer is required to create invoice.');
    action_redirect_back('modules/invoices/index.php');
}

if (!$lineTypes) {
    flash('error', 'Add at least one invoice line item.');
    action_redirect_back('modules/invoices/index.php');
}

$mysqli = db();
$lines = [];
$subtotal = 0.0;

for ($idx = 0; $idx < count($lineTypes); $idx++) {
    $type = strtolower(trim((string) ($lineTypes[$idx] ?? 'custom')));
    if (!in_array($type, ['item', 'service', 'custom'], true)) {
        $type = 'custom';
    }

    $qty = (int) ($lineQuantities[$idx] ?? 0);
    $rate = (float) ($lineRates[$idx] ?? 0);
    if ($qty <= 0) {
        continue;
    }
    if ($rate < 0) {
        $rate = 0;
    }

    $referenceId = null;
    $description = trim((string) ($lineDescriptions[$idx] ?? ''));

    if ($type === 'item') {
        $itemId = (int) ($lineItemIds[$idx] ?? 0);
        if ($itemId <= 0) {
            continue;
        }
        $itemStmt = $mysqli->prepare('SELECT item_name FROM items WHERE id = ? AND tenant_id = ? LIMIT 1');
        $itemStmt->bind_param('ii', $itemId, $tenantId);
        $itemStmt->execute();
        $item = $itemStmt->get_result()->fetch_assoc();
        $itemStmt->close();
        if (!$item) {
            continue;
        }
        $chargeType = strtolower(trim((string) ($lineChargeTypes[$idx] ?? 'hire')));
        if (!in_array($chargeType, ['hire', 'buy'], true)) {
            $chargeType = 'hire';
        }
        $referenceId = $itemId;
        $description = (string) $item['item_name'] . ' (' . ucfirst($chargeType) . ')';
    } elseif ($type === 'service') {
        $serviceId = (int) ($lineServiceIds[$idx] ?? 0);
        if ($serviceId <= 0) {
            continue;
        }
        $serviceStmt = $mysqli->prepare('SELECT service_name FROM services WHERE id = ? AND tenant_id = ? LIMIT 1');
        $serviceStmt->bind_param('ii', $serviceId, $tenantId);
        $serviceStmt->execute();
        $service = $serviceStmt->get_result()->fetch_assoc();
        $serviceStmt->close();
        if (!$service) {
            continue;
        }
        $referenceId = $serviceId;
        if ($description === '') {
            $description = (string) $service['service_name'];
        }
    } elseif ($description === '') {
        continue;
    }

    $amount = $qty * $rate;
    $subtotal += $amount;
    $lines[] = [
        'type' => $type,
        'reference_id' => $referenceId,
        'description' => $description,
        'quantity' => $qty,
        'rate' => $rate,
        'amount' => $amount,
    ];
}

if (!$lines) {
    flash('error', 'Please add valid line items before saving the invoice.');
    action_redirect_back('modules/invoices/index.php');
}

$total = $subtotal + $taxAmount;
$invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(md5((string) microtime(true)), 0, 6));
$createdBy = (int) auth_user()['id'];
$balance = $total;

$stmt = $mysqli->prepare('INSERT INTO invoices (tenant_id, booking_id, customer_id, invoice_no, issue_date, due_date, subtotal, tax_amount, total_amount, amount_paid, balance_amount, invoice_status, created_by, created_at, updated_at) VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?, 0, ?, "draft", ?, NOW(), NOW())');
$stmt->bind_param('iiisssdddii', $tenantId, $bookingId, $customerId, $invoiceNo, $issueDate, $dueDate, $subtotal, $taxAmount, $total, $balance, $createdBy);
$stmt->execute();
$invoiceId = (int) $stmt->insert_id;
$stmt->close();

$lineStmt = $mysqli->prepare('INSERT INTO invoice_items (tenant_id, invoice_id, line_type, reference_id, line_description, quantity, rate, amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
foreach ($lines as $line) {
    $lineType = (string) $line['type'];
    $referenceId = $line['reference_id'];
    $lineDescription = (string) $line['description'];
    $quantity = (int) $line['quantity'];
    $rate = (float) $line['rate'];
    $amount = (float) $line['amount'];
    $lineStmt->bind_param('iisisiid', $tenantId, $invoiceId, $lineType, $referenceId, $lineDescription, $quantity, $rate, $amount);
    $lineStmt->execute();
}
$lineStmt->close();

audit_log('invoices', 'create', 'invoices', $invoiceId, null, ['invoice_no' => $invoiceNo, 'total' => $total]);
flash('success', 'Invoice created in draft state.');
action_redirect_back('modules/invoices/index.php');
