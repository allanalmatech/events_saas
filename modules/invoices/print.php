<?php
require_once __DIR__ . '/../../includes/functions.php';
require_tenant_user();
require_permission('invoices.print');

$tenantId = (int) current_tenant_id();
$invoiceId = (int) get_str('id', '0');

if ($invoiceId <= 0) {
    exit('Invoice not specified.');
}

$stmt = db()->prepare('SELECT i.*, c.full_name AS customer_name, c.phone AS customer_phone, t.business_name, t.email AS business_email, t.phone AS business_phone FROM invoices i INNER JOIN customers c ON c.id = i.customer_id INNER JOIN tenants t ON t.id = i.tenant_id WHERE i.id = ? AND i.tenant_id = ? LIMIT 1');
$stmt->bind_param('ii', $invoiceId, $tenantId);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

$lineItems = [];
$lineStmt = db()->prepare('SELECT line_type, line_description, quantity, rate, amount FROM invoice_items WHERE tenant_id = ? AND invoice_id = ? ORDER BY id ASC');
if ($lineStmt) {
    $lineStmt->bind_param('ii', $tenantId, $invoiceId);
    $lineStmt->execute();
    $lineItems = $lineStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $lineStmt->close();
}

if (!$invoice) {
    exit('Invoice not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice <?php echo e($invoice['invoice_no']); ?></title>
    <style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px}</style>
</head>
<body>
<h1><?php echo e($invoice['business_name']); ?></h1>
<h2>Invoice <?php echo e($invoice['invoice_no']); ?></h2>
<p>Customer: <?php echo e($invoice['customer_name']); ?> | Phone: <?php echo e($invoice['customer_phone']); ?></p>
<p>Issue Date: <?php echo e($invoice['issue_date']); ?> | Due Date: <?php echo e($invoice['due_date']); ?></p>
<h3>Items & Services</h3>
<table>
    <thead>
        <tr><th>Description</th><th>Type</th><th>Qty</th><th>Rate</th><th>Amount</th></tr>
    </thead>
    <tbody>
    <?php foreach ($lineItems as $line): ?>
        <tr>
            <td><?php echo e($line['line_description']); ?></td>
            <td><?php echo e($line['line_type']); ?></td>
            <td><?php echo (int) $line['quantity']; ?></td>
            <td><?php echo number_format((float) $line['rate'], 2); ?></td>
            <td><?php echo number_format((float) $line['amount'], 2); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$lineItems): ?><tr><td colspan="5">No line items.</td></tr><?php endif; ?>
    </tbody>
</table>
<table>
    <tr><th>Subtotal</th><td><?php echo number_format((float) $invoice['subtotal'], 2); ?></td></tr>
    <tr><th>Tax</th><td><?php echo number_format((float) $invoice['tax_amount'], 2); ?></td></tr>
    <tr><th>Total</th><td><?php echo number_format((float) $invoice['total_amount'], 2); ?></td></tr>
    <tr><th>Paid</th><td><?php echo number_format((float) $invoice['amount_paid'], 2); ?></td></tr>
    <tr><th>Balance</th><td><?php echo number_format((float) $invoice['balance_amount'], 2); ?></td></tr>
</table>
<script>window.print();</script>
</body>
</html>
