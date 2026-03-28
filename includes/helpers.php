<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = rtrim(APP_URL, '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function method_not_allowed(): void
{
    http_response_code(405);
    exit('Method Not Allowed');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['_csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_fail(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $value = (string) $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function now_sql(): string
{
    return date('Y-m-d H:i:s');
}

function active_nav(string $needle): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($uri, $needle) !== false ? 'active' : '';
}

function supported_timezones(): array
{
    return [
        'Africa/Nairobi',
        'Africa/Kampala',
        'Africa/Lagos',
        'Africa/Johannesburg',
        'UTC',
        'Europe/London',
        'Europe/Berlin',
        'Asia/Dubai',
        'Asia/Kolkata',
        'Asia/Singapore',
        'America/New_York',
        'America/Chicago',
        'America/Los_Angeles',
    ];
}

function platform_settings(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $defaults = [
        'saas_name' => APP_NAME,
        'footer_text' => '',
        'support_email' => '',
        'support_phone' => '',
        'default_timezone' => APP_TIMEZONE,
        'default_currency' => APP_CURRENCY,
        'allow_auto_lock' => true,
        'login_heading' => 'Sign In',
        'login_subheading' => 'Access your account',
        'login_theme' => 'earth',
        'login_cover_description' => '',
        'login_accounts_enabled' => false,
        'login_accounts_payload' => '',
        'system_logo_path' => '',
        'login_cover_image_path' => '',
    ];

    $file = __DIR__ . '/../storage/platform_settings.json';
    if (!file_exists($file)) {
        $cache = $defaults;
        return $cache;
    }

    $raw = file_get_contents($file);
    $json = $raw ? json_decode($raw, true) : null;
    if (!is_array($json)) {
        $cache = $defaults;
        return $cache;
    }

    $cache = array_merge($defaults, $json);
    return $cache;
}

function platform_setting(string $key, $default = '')
{
    $settings = platform_settings();
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function platform_saas_name(): string
{
    $name = trim((string) platform_setting('saas_name', APP_NAME));
    return $name !== '' ? $name : APP_NAME;
}
