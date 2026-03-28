<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function contains(string $file, string $needle): bool
{
    if (!file_exists($file)) {
        return false;
    }
    $content = (string) file_get_contents($file);
    return strpos($content, $needle) !== false;
}

function pass(string $name): void
{
    echo '[PASS] ' . $name . PHP_EOL;
}

function fail(string $name): void
{
    echo '[FAIL] ' . $name . PHP_EOL;
}

$checks = [];

$checks['Installer lock guard'] = contains($root . '/setup.php', 'install.lock') && contains($root . '/setup.php', 'if ($isInstalled)');
$checks['Migration idempotency baseline'] = contains($root . '/migrations/001_initial_schema.sql', 'CREATE TABLE IF NOT EXISTS migrations');
$checks['Tenant hard lock enforcement'] = contains($root . '/includes/tenant.php', 'enforce_tenant_hard_lock_for_module') && contains($root . '/templates/module_page.php', 'enforce_tenant_hard_lock_for_module');
$checks['Feature gate helpers'] = contains($root . '/includes/feature_limits.php', 'enforceFeatureForCurrentTenant') && contains($root . '/includes/feature_limits.php', 'enforceLimitForCurrentTenant');
$checks['Plan limit checks used in actions'] = contains($root . '/actions/save_user.php', 'enforceLimitForCurrentTenant') && contains($root . '/actions/save_booking.php', 'enforceLimitForCurrentTenant') && contains($root . '/actions/save_item.php', 'enforceLimitForCurrentTenant') && contains($root . '/actions/save_customer.php', 'enforceLimitForCurrentTenant');
$checks['Subscription state machine'] = contains($root . '/includes/subscription.php', 'subscription_allowed_transitions') && contains($root . '/actions/set_subscription_status.php', 'set_subscription_status');
$checks['Password reset flow'] = file_exists($root . '/forgot_password.php') && file_exists($root . '/reset_password.php') && file_exists($root . '/actions/request_password_reset.php') && file_exists($root . '/actions/reset_password.php');
$checks['Optional user overrides'] = file_exists($root . '/actions/save_user_override.php') && contains($root . '/modules/permissions/index.php', 'User Permission Override');
$checks['External provider registry'] = file_exists($root . '/actions/save_external_provider.php') && contains($root . '/modules/items/index.php', 'External Provider Registry');
$checks['Inventory and marketplace reports'] = contains($root . '/modules/reports/index.php', 'Top Hired Inventory') && contains($root . '/modules/reports/index.php', 'Marketplace Engagement By Tenant');

$ok = true;
foreach ($checks as $name => $state) {
    if ($state) {
        pass($name);
    } else {
        fail($name);
        $ok = false;
    }
}

exit($ok ? 0 : 1);
