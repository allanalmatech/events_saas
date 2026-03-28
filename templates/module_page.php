<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth();

$pageTitle = $pageTitle ?? 'Module';
$moduleKey = $moduleKey ?? 'dashboard';
$moduleDescription = $moduleDescription ?? 'Module workspace';
$modulePermission = $modulePermission ?? $moduleKey . '.view';

if (auth_role() === 'tenant_user' && !can($modulePermission) && !auth_user()['is_super_admin']) {
    http_response_code(403);
    exit('Missing permission: ' . e($modulePermission));
}

if (auth_role() === 'tenant_user') {
    enforce_tenant_hard_lock_for_module($moduleKey);

    $featureMap = [
        'calendar' => 'has_calendar',
        'reports' => 'has_reports',
        'marketplace' => 'has_marketplace',
        'messages' => 'has_internal_messaging',
        'broadcasts' => 'has_broadcasts',
        'audit_logs' => 'has_audit_log_access',
    ];

    if (isset($featureMap[$moduleKey])) {
        enforceFeatureForCurrentTenant($featureMap[$moduleKey], 'subscriptions');
    }
}

include __DIR__ . '/header.php';
?>
<div class="app">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-overlay" data-sidebar-close></div>
    <main class="main">
        <?php include __DIR__ . '/topbar.php'; ?>

        <?php if ($m = flash('error')): ?><div class="alert error"><?php echo e($m); ?></div><?php endif; ?>
        <?php if ($m = flash('success')): ?><div class="alert success"><?php echo e($m); ?></div><?php endif; ?>

        <section class="glass" style="padding:18px; margin-bottom:16px;">
            <h2 style="margin-top:0;"><?php echo e($pageTitle); ?></h2>
            <p class="muted" style="margin-bottom:0;"><?php echo e($moduleDescription); ?></p>
        </section>

        <?php if (isset($contentRenderer) && is_callable($contentRenderer)): ?>
            <?php $contentRenderer(); ?>
        <?php else: ?>
            <section class="card">
                <p class="muted" style="margin:0;">This module layout is ready for full business workflows, forms, and reports.</p>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php include __DIR__ . '/footer.php'; ?>
