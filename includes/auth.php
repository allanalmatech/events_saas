<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function auth_user(): ?array
{
    return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user']) ? $_SESSION['auth_user'] : null;
}

function auth_check(): bool
{
    return auth_user() !== null;
}

function auth_role(): string
{
    $user = auth_user();
    return $user['role_type'] ?? 'guest';
}

function require_auth(): void
{
    if (!auth_check()) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function require_director(): void
{
    require_auth();
    if (auth_role() !== 'director') {
        http_response_code(403);
        exit('Director access required.');
    }
}

function require_tenant_user(): void
{
    require_auth();
    if (auth_role() !== 'tenant_user') {
        http_response_code(403);
        exit('Tenant user access required.');
    }
}

function director_profile_image_path(int $directorId): string
{
    if ($directorId <= 0) {
        return '';
    }

    $file = __DIR__ . '/../storage/director_profile_settings.json';
    if (!file_exists($file)) {
        return '';
    }

    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return '';
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return '';
    }

    $row = $json[(string) $directorId] ?? null;
    if (!is_array($row)) {
        return '';
    }

    return trim((string) ($row['profile_image_path'] ?? ''));
}

function auth_login_director(string $email, string $password): bool
{
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT id, full_name, email, password_hash, status FROM director_users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || $row['status'] !== 'active') {
        return false;
    }

    if (!password_verify($password, (string) $row['password_hash'])) {
        return false;
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $row['id'],
        'name' => $row['full_name'],
        'email' => $row['email'],
        'role_type' => 'director',
        'tenant_id' => null,
        'is_super_admin' => false,
        'profile_image_path' => director_profile_image_path((int) $row['id']),
    ];

    return true;
}

function auth_login_tenant_user(string $email, string $password): bool
{
    $mysqli = db();
    $stmt = $mysqli->prepare('SELECT u.id, u.tenant_id, u.role_id, u.full_name, u.email, u.password_hash, u.account_status, u.is_super_admin, u.profile_image_path, r.role_name, t.account_status AS tenant_status, t.timezone FROM tenant_users u INNER JOIN tenants t ON t.id = u.tenant_id LEFT JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || !in_array($row['account_status'], ['active'], true) || !in_array($row['tenant_status'], ['active', 'locked'], true)) {
        return false;
    }

    if (!password_verify($password, (string) $row['password_hash'])) {
        return false;
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $row['id'],
        'name' => $row['full_name'],
        'email' => $row['email'],
        'role_type' => 'tenant_user',
        'tenant_id' => (int) $row['tenant_id'],
        'timezone' => (string) ($row['timezone'] ?? APP_TIMEZONE),
        'role_id' => $row['role_id'] !== null ? (int) $row['role_id'] : null,
        'role_name' => $row['role_name'] ?? '',
        'is_super_admin' => (bool) $row['is_super_admin'],
        'profile_image_path' => $row['profile_image_path'] ?? '',
    ];

    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
}
