<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function current_tenant_id(): ?int
{
    $user = auth_user();
    if (!$user || !isset($user['tenant_id']) || $user['tenant_id'] === null) {
        return null;
    }

    return (int) $user['tenant_id'];
}

function tenant_scope_sql(string $field = 'tenant_id'): string
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return '1=0';
    }

    return $field . ' = ' . (int) $tenantId;
}

function current_tenant_branding(): array
{
    static $cache = [];

    $tenantId = current_tenant_id();
    if ($tenantId === null || $tenantId <= 0) {
        return [
            'business_name' => APP_NAME,
            'business_initials' => '',
            'logo_path' => '',
        ];
    }

    if (isset($cache[$tenantId])) {
        return $cache[$tenantId];
    }

    $branding = [
        'business_name' => APP_NAME,
        'business_initials' => '',
        'logo_path' => '',
    ];

    $stmt = db()->prepare('SELECT business_name, business_initials, logo_path FROM tenants WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $branding['business_name'] = (string) ($row['business_name'] ?? APP_NAME);
            $branding['business_initials'] = (string) ($row['business_initials'] ?? '');
            $branding['logo_path'] = (string) ($row['logo_path'] ?? '');
        }
    }

    $cache[$tenantId] = $branding;
    return $branding;
}

function tenant_lock_state(int $tenantId): array
{
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT lock_mode, amount_due, due_date, grace_expires_at FROM tenant_account_locks WHERE tenant_id = ? AND is_active = 1 ORDER BY locked_at DESC LIMIT 1');
    if (!$stmt) {
        return ['locked' => false, 'mode' => null];
    }

    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return ['locked' => false, 'mode' => null];
    }

    return [
        'locked' => true,
        'mode' => $row['lock_mode'],
        'amount_due' => $row['amount_due'],
        'due_date' => $row['due_date'],
        'grace_expires_at' => $row['grace_expires_at'],
    ];
}

function enforce_tenant_lock_for_write(): void
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return;
    }

    $lock = tenant_lock_state($tenantId);
    if (!$lock['locked']) {
        return;
    }

    if ($lock['mode'] === 'hard') {
        flash('error', 'Account is hard-locked. Access billing/support only.');
        redirect('modules/billing/index.php');
    }

    flash('error', 'Account is soft-locked. New record creation is disabled until billing is cleared.');
    redirect('modules/subscriptions/index.php');
}

function enforce_tenant_hard_lock_for_module(string $moduleKey): void
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return;
    }

    $lock = tenant_lock_state($tenantId);
    if (!$lock['locked'] || $lock['mode'] !== 'hard') {
        return;
    }

    $allowedModules = ['billing', 'subscriptions', 'tickets', 'notifications'];
    if (in_array($moduleKey, $allowedModules, true)) {
        return;
    }

    flash('error', 'Your account is hard-locked. Access is limited to billing and support modules.');
    redirect('modules/billing/index.php');
}
