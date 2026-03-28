<?php
declare(strict_types=1);

require_once __DIR__ . '/tenant.php';

function get_active_subscription_plan_id(int $tenantId): ?int
{
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT plan_id FROM tenant_subscriptions WHERE tenant_id = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ? (int) $row['plan_id'] : null;
}

function canUseFeature(int $tenantId, string $featureKey): bool
{
    $mysqli = db();

    $stmt = $mysqli->prepare('SELECT bool_value FROM tenant_feature_overrides WHERE tenant_id = ? AND feature_key = ? AND override_type = "boolean" AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('is', $tenantId, $featureKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            return (int) $row['bool_value'] === 1;
        }
    }

    $planId = get_active_subscription_plan_id($tenantId);
    if ($planId === null) {
        return false;
    }

    $stmt = $mysqli->prepare('SELECT bool_value FROM subscription_plan_features WHERE plan_id = ? AND feature_key = ? AND feature_type = "boolean" LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $planId, $featureKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? (int) $row['bool_value'] === 1 : false;
}

function getPlanLimit(int $tenantId, string $limitKey): ?int
{
    $mysqli = db();

    $stmt = $mysqli->prepare('SELECT int_value FROM tenant_feature_overrides WHERE tenant_id = ? AND feature_key = ? AND override_type = "limit" AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('is', $tenantId, $limitKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            return $row['int_value'] !== null ? (int) $row['int_value'] : null;
        }
    }

    $planId = get_active_subscription_plan_id($tenantId);
    if ($planId === null) {
        return 0;
    }

    $stmt = $mysqli->prepare('SELECT int_value FROM subscription_plan_features WHERE plan_id = ? AND feature_key = ? AND feature_type = "limit" LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('is', $planId, $limitKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? ($row['int_value'] === null ? null : (int) $row['int_value']) : 0;
}

function hasExceededLimit(int $tenantId, string $limitKey, int $currentCount): bool
{
    $limit = getPlanLimit($tenantId, $limitKey);
    if ($limit === null) {
        return false;
    }
    return $currentCount >= $limit;
}

function enforceFeatureForCurrentTenant(string $featureKey, string $moduleFallback = 'subscriptions'): void
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return;
    }

    if (!canUseFeature($tenantId, $featureKey)) {
        flash('error', 'Your subscription plan does not include: ' . $featureKey);
        redirect('modules/' . $moduleFallback . '/index.php');
    }
}

function enforceLimitForCurrentTenant(string $limitKey, int $currentCount, string $moduleFallback = 'subscriptions'): void
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return;
    }

    if (hasExceededLimit($tenantId, $limitKey, $currentCount)) {
        flash('error', 'Plan limit reached for: ' . $limitKey);
        redirect('modules/' . $moduleFallback . '/index.php');
    }
}
