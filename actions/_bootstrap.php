<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

function action_require_post(): void
{
    if (!is_post()) {
        method_not_allowed();
    }
    verify_csrf_or_fail();
}

function action_redirect_back(string $fallback = 'dashboard.php'): void
{
    $target = $_SERVER['HTTP_REFERER'] ?? app_url($fallback);
    header('Location: ' . $target);
    exit;
}
