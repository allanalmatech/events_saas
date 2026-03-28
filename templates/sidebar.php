<?php
$user = auth_user();
$isDirector = $user && $user['role_type'] === 'director';
$platform = platform_settings();
$directorLogoPath = trim((string) ($platform['system_logo_path'] ?? ''));
$branding = $isDirector ? ['business_name' => platform_saas_name(), 'business_initials' => '', 'logo_path' => $directorLogoPath] : current_tenant_branding();
$logoPath = trim((string) ($branding['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? app_url(ltrim($logoPath, '/')) : '';
$brandName = trim((string) ($branding['business_name'] ?? APP_NAME));
$brandShort = trim((string) ($branding['business_initials'] ?? ''));
if ($brandShort === '') {
    $words = preg_split('/\s+/', $brandName) ?: [];
    $first = isset($words[0][0]) ? $words[0][0] : '';
    $second = isset($words[1][0]) ? $words[1][0] : '';
    $brandShort = strtoupper($first . $second);
    if ($brandShort === '') {
        $brandShort = strtoupper(substr($brandName, 0, 2));
    }
}

if (!function_exists('is_any_active')) {
    function is_any_active(array $needles): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($uri, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sidebar_link')) {
    function sidebar_link(string $href, string $label, string $activeClass = '', string $iconClass = 'fa-circle', string $extraClass = ''): void
    {
        $cls = trim($activeClass . ' ' . $extraClass);
        echo '<a class="' . e($cls) . '" href="' . e($href) . '" title="' . e($label) . '">';
        echo '<span class="icon"><i class="fa-solid ' . e($iconClass) . '"></i></span>';
        echo '<span class="label">' . e($label) . '</span>';
        echo '</a>';
    }
}
?>
<aside class="sidebar">
    <div class="brand">
        <?php if ($logoUrl !== ''): ?><img src="<?php echo e($logoUrl); ?>" alt="Business logo" class="brand-logo"><?php endif; ?>
        <span class="brand-full"><?php echo e($brandName); ?></span>
        <span class="brand-short"><?php echo e($brandShort); ?></span>
    </div>
    <div class="badge"><?php echo $isDirector ? 'Director' : 'Tenant'; ?> Portal</div>

    <nav class="menu">
        <?php sidebar_link(app_url('dashboard.php'), 'Dashboard', active_nav('/dashboard'), 'fa-gauge-high'); ?>

        <?php if ($isDirector): ?>
            <?php $govOpen = is_any_active(['/modules/tenants/', '/modules/plans/', '/modules/subscriptions/', '/modules/billing/']); ?>
            <div class="menu-group <?php echo $govOpen ? 'open' : ''; ?>">
                <button class="menu-group-toggle" type="button" data-submenu-toggle title="Governance">
                    <span class="icon"><i class="fa-solid fa-building-shield"></i></span>
                    <span class="label">Governance</span>
                    <span class="caret"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="submenu">
                    <?php sidebar_link(app_url('modules/tenants/index.php'), 'Tenants', active_nav('/modules/tenants/'), 'fa-building', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/plans/index.php'), 'Plans', active_nav('/modules/plans/'), 'fa-layer-group', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/subscriptions/index.php'), 'Subscriptions', active_nav('/modules/subscriptions/'), 'fa-arrows-rotate', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/billing/index.php'), 'SaaS Billing', active_nav('/modules/billing/'), 'fa-money-check-dollar', 'submenu-link'); ?>
                </div>
            </div>

            <?php $supOpen = is_any_active(['/modules/tickets/', '/modules/reports/']); ?>
            <div class="menu-group <?php echo $supOpen ? 'open' : ''; ?>">
                <button class="menu-group-toggle" type="button" data-submenu-toggle title="Support & Analytics">
                    <span class="icon"><i class="fa-solid fa-headset"></i></span>
                    <span class="label">Support & Analytics</span>
                    <span class="caret"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="submenu">
                    <?php sidebar_link(app_url('modules/tickets/index.php'), 'Support Tickets', active_nav('/modules/tickets/'), 'fa-life-ring', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/reports/index.php'), 'Platform Reports', active_nav('/modules/reports/'), 'fa-chart-pie', 'submenu-link'); ?>
                </div>
            </div>
        <?php else: ?>
            <?php $peopleOpen = is_any_active(['/modules/users/', '/modules/roles/', '/modules/customers/']); ?>
            <div class="menu-group <?php echo $peopleOpen ? 'open' : ''; ?>">
                <button class="menu-group-toggle" type="button" data-submenu-toggle title="People">
                    <span class="icon"><i class="fa-solid fa-users"></i></span>
                    <span class="label">People</span>
                    <span class="caret"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="submenu">
                    <?php sidebar_link(app_url('modules/users/index.php'), 'Users', active_nav('/modules/users/'), 'fa-user-group', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/roles/index.php'), 'Roles', active_nav('/modules/roles/'), 'fa-user-shield', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/customers/index.php'), 'Customers', active_nav('/modules/customers/'), 'fa-address-book', 'submenu-link'); ?>
                </div>
            </div>

            <?php $opsOpen = is_any_active(['/modules/items/', '/modules/services/', '/modules/bookings/', '/modules/returns/', '/modules/workers/', '/modules/calendar/']); ?>
            <div class="menu-group <?php echo $opsOpen ? 'open' : ''; ?>">
                <button class="menu-group-toggle" type="button" data-submenu-toggle title="Operations">
                    <span class="icon"><i class="fa-solid fa-briefcase"></i></span>
                    <span class="label">Operations</span>
                    <span class="caret"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="submenu">
                    <?php sidebar_link(app_url('modules/items/index.php'), 'Inventory', active_nav('/modules/items/'), 'fa-boxes-stacked', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/services/index.php'), 'Services', active_nav('/modules/services/'), 'fa-screwdriver-wrench', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/bookings/index.php'), 'Bookings', active_nav('/modules/bookings/'), 'fa-calendar-check', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/returns/index.php'), 'Returns', active_nav('/modules/returns/'), 'fa-rotate-left', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/workers/index.php'), 'Workers', active_nav('/modules/workers/'), 'fa-people-carry-box', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/calendar/index.php'), 'Calendar', active_nav('/modules/calendar/'), 'fa-calendar-days', 'submenu-link'); ?>
                </div>
            </div>

            <?php $finOpen = is_any_active(['/modules/invoices/', '/modules/receipts/', '/modules/payments/']); ?>
            <div class="menu-group <?php echo $finOpen ? 'open' : ''; ?>">
                <button class="menu-group-toggle" type="button" data-submenu-toggle title="Finance">
                    <span class="icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                    <span class="label">Finance</span>
                    <span class="caret"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="submenu">
                    <?php sidebar_link(app_url('modules/invoices/index.php'), 'Invoices', active_nav('/modules/invoices/'), 'fa-file-invoice-dollar', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/receipts/index.php'), 'Receipts', active_nav('/modules/receipts/'), 'fa-receipt', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/payments/index.php'), 'Payments', active_nav('/modules/payments/'), 'fa-wallet', 'submenu-link'); ?>
                </div>
            </div>

            <?php $commOpen = is_any_active(['/modules/marketplace/', '/modules/messages/', '/modules/broadcasts/']); ?>
            <div class="menu-group <?php echo $commOpen ? 'open' : ''; ?>">
                <button class="menu-group-toggle" type="button" data-submenu-toggle title="Community">
                    <span class="icon"><i class="fa-solid fa-globe"></i></span>
                    <span class="label">Community</span>
                    <span class="caret"><i class="fa-solid fa-chevron-right"></i></span>
                </button>
                <div class="submenu">
                    <?php sidebar_link(app_url('modules/marketplace/index.php'), 'Marketplace', active_nav('/modules/marketplace/'), 'fa-store', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/messages/index.php'), 'Messages', active_nav('/modules/messages/'), 'fa-comments', 'submenu-link'); ?>
                    <?php sidebar_link(app_url('modules/broadcasts/index.php'), 'Broadcasts', active_nav('/modules/broadcasts/'), 'fa-bullhorn', 'submenu-link'); ?>
                </div>
            </div>

            <?php sidebar_link(app_url('modules/reports/index.php'), 'Reports', active_nav('/modules/reports/'), 'fa-chart-line'); ?>
        <?php endif; ?>

        <?php $sysOpen = is_any_active(['/modules/settings/', '/modules/audit_logs/']); ?>
        <div class="menu-group <?php echo $sysOpen ? 'open' : ''; ?>">
            <button class="menu-group-toggle" type="button" data-submenu-toggle title="System">
                <span class="icon"><i class="fa-solid fa-gear"></i></span>
                <span class="label">System</span>
                <span class="caret"><i class="fa-solid fa-chevron-right"></i></span>
            </button>
            <div class="submenu">
                <?php sidebar_link(app_url('modules/settings/index.php'), 'Settings', active_nav('/modules/settings/'), 'fa-sliders', 'submenu-link'); ?>
                <?php sidebar_link(app_url('modules/audit_logs/index.php'), 'Audit Logs', active_nav('/modules/audit_logs/'), 'fa-clipboard-list', 'submenu-link'); ?>
            </div>
        </div>
    </nav>
</aside>
