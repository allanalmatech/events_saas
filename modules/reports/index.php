<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Reports';
$moduleKey = 'reports';
$modulePermission = 'reports.view';
$moduleDescription = 'Revenue, stock movement, booking trends, outsourcing insights, and accountability analytics.';

$contentRenderer = function (): void {
    $tenantId = current_tenant_id();
    $isDirector = auth_role() === 'director';
    $stats = [
        'bookings' => 0,
        'invoices' => 0,
        'payments' => 0,
        'outsourced' => 0,
        'marketplace_ads' => 0,
        'messages' => 0,
    ];
    $topHired = [];
    $topOutsourced = [];
    $marketplaceEngagement = [];

    if ($isDirector && ($mysqli = db_try())) {
        $queries = [
            'bookings' => 'SELECT COUNT(*) c FROM bookings',
            'invoices' => 'SELECT COUNT(*) c FROM invoices',
            'payments' => 'SELECT COUNT(*) c FROM payments',
            'outsourced' => 'SELECT COUNT(*) c FROM outsourced_items',
            'marketplace_ads' => 'SELECT COUNT(*) c FROM marketplace_catalogue',
            'messages' => 'SELECT COUNT(*) c FROM tenant_messages',
        ];
        foreach ($queries as $key => $sql) {
            $res = $mysqli->query($sql);
            $row = $res ? $res->fetch_assoc() : null;
            $stats[$key] = (int) ($row['c'] ?? 0);
        }

        $engSql = 'SELECT t.business_name, COUNT(tm.id) message_count, (SELECT COUNT(*) FROM marketplace_catalogue mc WHERE mc.tenant_id = t.id AND mc.is_active = 1) AS active_ads FROM tenants t LEFT JOIN tenant_messages tm ON tm.from_tenant_id = t.id OR tm.to_tenant_id = t.id GROUP BY t.id, t.business_name ORDER BY message_count DESC LIMIT 15';
        $engRes = $mysqli->query($engSql);
        $marketplaceEngagement = $engRes ? $engRes->fetch_all(MYSQLI_ASSOC) : [];
    }

    if (!$isDirector && $tenantId && ($mysqli = db_try())) {
        $queries = [
            'bookings' => 'SELECT COUNT(*) c FROM bookings WHERE tenant_id = ' . (int) $tenantId,
            'invoices' => 'SELECT COUNT(*) c FROM invoices WHERE tenant_id = ' . (int) $tenantId,
            'payments' => 'SELECT COUNT(*) c FROM payments WHERE tenant_id = ' . (int) $tenantId,
            'outsourced' => 'SELECT COUNT(*) c FROM outsourced_items WHERE tenant_id = ' . (int) $tenantId,
            'marketplace_ads' => 'SELECT COUNT(*) c FROM marketplace_catalogue WHERE tenant_id = ' . (int) $tenantId,
            'messages' => 'SELECT COUNT(*) c FROM tenant_messages WHERE from_tenant_id = ' . (int) $tenantId . ' OR to_tenant_id = ' . (int) $tenantId,
        ];

        foreach ($queries as $key => $sql) {
            $res = $mysqli->query($sql);
            $row = $res ? $res->fetch_assoc() : null;
            $stats[$key] = (int) ($row['c'] ?? 0);
        }

        $hiredSql = 'SELECT i.item_name, SUM(bi.quantity) total_qty FROM booking_items bi INNER JOIN items i ON i.id = bi.item_id WHERE bi.tenant_id = ' . (int) $tenantId . ' GROUP BY i.item_name ORDER BY total_qty DESC LIMIT 5';
        $hiredRes = $mysqli->query($hiredSql);
        $topHired = $hiredRes ? $hiredRes->fetch_all(MYSQLI_ASSOC) : [];

        $outsSql = 'SELECT item_name, SUM(quantity) total_qty FROM outsourced_items WHERE tenant_id = ' . (int) $tenantId . ' GROUP BY item_name ORDER BY total_qty DESC LIMIT 5';
        $outsRes = $mysqli->query($outsSql);
        $topOutsourced = $outsRes ? $outsRes->fetch_all(MYSQLI_ASSOC) : [];
    }
    ?>
    <section class="grid cols-4">
        <article class="card"><div class="muted">Bookings</div><div class="kpi"><?php echo (int) $stats['bookings']; ?></div></article>
        <article class="card"><div class="muted">Invoices</div><div class="kpi"><?php echo (int) $stats['invoices']; ?></div></article>
        <article class="card"><div class="muted">Payments</div><div class="kpi"><?php echo (int) $stats['payments']; ?></div></article>
        <article class="card"><div class="muted">Outsourced Items</div><div class="kpi"><?php echo (int) $stats['outsourced']; ?></div></article>
        <article class="card"><div class="muted">Marketplace Ads</div><div class="kpi"><?php echo (int) $stats['marketplace_ads']; ?></div></article>
        <article class="card"><div class="muted">Message Traffic</div><div class="kpi"><?php echo (int) $stats['messages']; ?></div></article>
    </section>

    <?php if ($isDirector): ?>
    <section class="card" style="margin-top:14px;">
        <h3 style="margin-top:0;">Marketplace Engagement By Tenant</h3>
        <table class="table">
            <thead><tr><th>Tenant</th><th>Message Traffic</th><th>Active Ads</th></tr></thead>
            <tbody>
            <?php foreach ($marketplaceEngagement as $row): ?><tr><td><?php echo e($row['business_name']); ?></td><td><?php echo (int) $row['message_count']; ?></td><td><?php echo (int) $row['active_ads']; ?></td></tr><?php endforeach; ?>
            <?php if (!$marketplaceEngagement): ?><tr><td colspan="3" class="muted">No marketplace engagement data yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>
    <section class="card" style="margin-top:14px;">
        <h3 style="margin-top:0;">Smart Suggestion</h3>
        <p class="muted" style="margin-bottom:0;">If outsourced items keep increasing for the same category, recommend stock purchase for cost reduction and margin protection.</p>
    </section>

    <section class="grid cols-2" style="margin-top:14px;">
        <article class="card">
            <h3 style="margin-top:0;">Top Hired Inventory</h3>
            <table class="table">
                <thead><tr><th>Item</th><th>Total Qty</th></tr></thead>
                <tbody>
                <?php foreach ($topHired as $row): ?><tr><td><?php echo e($row['item_name']); ?></td><td><?php echo (int) $row['total_qty']; ?></td></tr><?php endforeach; ?>
                <?php if (!$topHired): ?><tr><td colspan="2" class="muted">No inventory usage yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Top Outsourced Items</h3>
            <table class="table">
                <thead><tr><th>Item</th><th>Total Qty</th></tr></thead>
                <tbody>
                <?php foreach ($topOutsourced as $row): ?><tr><td><?php echo e($row['item_name']); ?></td><td><?php echo (int) $row['total_qty']; ?></td></tr><?php endforeach; ?>
                <?php if (!$topOutsourced): ?><tr><td colspan="2" class="muted">No outsourcing records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
