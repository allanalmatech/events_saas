<?php
require_once __DIR__ . '/../../includes/functions.php';
require_tenant_user();
require_permission('receipts.print');

$tenantId = (int) current_tenant_id();
$receiptId = (int) get_str('id', '0');

if ($receiptId <= 0) {
    exit('Receipt not specified.');
}

$stmt = db()->prepare('SELECT r.*, i.invoice_no, c.full_name AS customer_name, c.phone AS customer_phone, t.business_name, t.tagline AS business_description, t.email AS business_email, t.phone AS business_phone, t.address AS business_address, t.logo_path AS business_logo_path, ts.receipt_footer, tu.full_name AS issued_by_name FROM receipts r INNER JOIN invoices i ON i.id = r.invoice_id INNER JOIN customers c ON c.id = i.customer_id INNER JOIN tenants t ON t.id = r.tenant_id LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id LEFT JOIN tenant_users tu ON tu.id = r.received_by AND tu.tenant_id = r.tenant_id WHERE r.id = ? AND r.tenant_id = ? LIMIT 1');
$stmt->bind_param('ii', $receiptId, $tenantId);
$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receipt) {
    exit('Receipt not found.');
}
 
$paymentMethod = (string) ($receipt['payment_method'] ?? '');
$paymentLabels = [
    'cash' => 'Cash',
    'mobile_money' => 'Mobile Money',
    'bank_transfer' => 'Bank Transfer',
    'card' => 'Card',
    'cheque' => 'Cheque',
    'other' => 'Other',
];
$paymentMethodLabel = $paymentLabels[$paymentMethod] ?? ucfirst(str_replace('_', ' ', $paymentMethod));
$receiptFooter = trim((string) ($receipt['receipt_footer'] ?? ''));
$logoPath = trim((string) ($receipt['business_logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? app_url(ltrim($logoPath, '/')) : '';

$platform = [
    'saas_name' => 'Event SaaS',
    'support_phone' => '',
];
$platformFile = __DIR__ . '/../../storage/platform_settings.json';
if (file_exists($platformFile)) {
    $raw = file_get_contents($platformFile);
    $json = $raw ? json_decode($raw, true) : null;
    if (is_array($json)) {
        $platform = array_merge($platform, $json);
    }
}

$creditName = trim((string) ($platform['saas_name'] ?? 'Event SaaS'));
if ($creditName === '') {
    $creditName = 'Event SaaS';
}
$creditPhone = trim((string) ($platform['support_phone'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt <?php echo e($receipt['receipt_no']); ?></title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { font-family: "Courier New", Courier, monospace; color: #111; background: #fff; }
        .receipt { width: 72mm; margin: 0 auto; padding: 4mm 0; font-size: 11px; line-height: 1.4; }
        .center { text-align: center; }
        .title { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
        .muted { color: #444; }
        .hr { border-top: 1px dashed #111; margin: 6px 0; }
        .head { display:flex; gap:8px; align-items:flex-start; }
        .logo { width: 18mm; height: 18mm; object-fit: contain; border: 1px solid #ccc; padding: 1mm; box-sizing: border-box; }
        .biz-meta { flex:1; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .row b { font-weight: 700; }
        .footer { margin-top: 8px; white-space: pre-wrap; }
        .credit { margin-top: 8px; font-size: 10px; text-align:center; color:#333; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="head">
            <?php if ($logoUrl !== ''): ?><img src="<?php echo e($logoUrl); ?>" alt="Business logo" class="logo"><?php endif; ?>
            <div class="biz-meta">
                <div class="title"><?php echo e($receipt['business_name']); ?></div>
                <?php if (!empty($receipt['business_phone'])): ?><div class="muted">Tel: <?php echo e($receipt['business_phone']); ?></div><?php endif; ?>
                <?php if (!empty($receipt['business_email'])): ?><div class="muted"><?php echo e($receipt['business_email']); ?></div><?php endif; ?>
                <?php if (!empty($receipt['business_address'])): ?><div class="muted"><?php echo e($receipt['business_address']); ?></div><?php endif; ?>
            </div>
        </div>
        <?php if (!empty($receipt['business_description'])): ?><div class="center muted" style="margin-top:4px;"><?php echo e($receipt['business_description']); ?></div><?php endif; ?>

        <div class="hr"></div>
        <div class="center"><b>PAYMENT RECEIPT</b></div>
        <div class="center muted">No: <?php echo e($receipt['receipt_no']); ?></div>
        <div class="center muted">Date: <?php echo e($receipt['receipt_date']); ?></div>

        <div class="hr"></div>
        <div><b>Customer:</b> <?php echo e($receipt['customer_name']); ?></div>
        <?php if (!empty($receipt['customer_phone'])): ?><div><b>Customer Tel:</b> <?php echo e($receipt['customer_phone']); ?></div><?php endif; ?>
        <div><b>Invoice:</b> <?php echo e($receipt['invoice_no']); ?></div>
        <?php if (!empty($receipt['issued_by_name'])): ?><div><b>Issued By:</b> <?php echo e($receipt['issued_by_name']); ?></div><?php endif; ?>

        <div class="hr"></div>
        <div class="row"><span>Amount Paid</span><b><?php echo number_format((float) $receipt['amount_paid'], 2); ?></b></div>
        <div class="row"><span>Method</span><span><?php echo e($paymentMethodLabel); ?></span></div>
        <?php if (!empty($receipt['payment_reference'])): ?><div class="row"><span>Reference</span><span><?php echo e($receipt['payment_reference']); ?></span></div><?php endif; ?>
        <div class="row"><span>Balance After</span><b><?php echo number_format((float) $receipt['balance_after'], 2); ?></b></div>

        <?php if ($receiptFooter !== ''): ?>
            <div class="hr"></div>
            <div class="footer center"><?php echo nl2br(e($receiptFooter)); ?></div>
        <?php endif; ?>

        <div class="hr"></div>
        <div class="credit"><?php echo e($creditName); ?><?php echo $creditPhone !== '' ? ' | ' . e($creditPhone) : ''; ?></div>
    </div>
    <script>window.print();</script>
</body>
</html>
