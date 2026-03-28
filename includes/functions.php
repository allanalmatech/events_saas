<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/feature_limits.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/theme.php';

function app_boot(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $tz = $_SESSION['auth_user']['timezone'] ?? null;
    if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
}

app_boot();
