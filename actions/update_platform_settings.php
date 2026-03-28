<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_director();

$storageDir = __DIR__ . '/../storage';
$file = $storageDir . '/platform_settings.json';
$assetDir = $storageDir . '/platform_assets';

if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
    flash('error', 'Could not prepare storage directory.');
    action_redirect_back('modules/settings/index.php');
}
if (!is_dir($assetDir) && !mkdir($assetDir, 0755, true) && !is_dir($assetDir)) {
    flash('error', 'Could not prepare platform assets directory.');
    action_redirect_back('modules/settings/index.php');
}

$existing = platform_settings();

function store_platform_image(string $inputName, string $prefix, string $assetDir): ?string
{
    if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
        return null;
    }
    $upload = $_FILES[$inputName];
    $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Could not upload image for ' . $inputName . '.');
    }

    $tmpName = (string) ($upload['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid uploaded image for ' . $inputName . '.');
    }

    $imageInfo = @getimagesize($tmpName);
    $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($extMap[$mime])) {
        throw new RuntimeException('Unsupported image format for ' . $inputName . '.');
    }

    $filename = $prefix . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extMap[$mime];
    $target = $assetDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('Could not save image for ' . $inputName . '.');
    }

    return 'storage/platform_assets/' . $filename;
}

$saasName = post_str('saas_name', (string) ($existing['saas_name'] ?? APP_NAME));
$footerText = post_str('footer_text', (string) ($existing['footer_text'] ?? ''));
$supportEmail = post_str('support_email', (string) ($existing['support_email'] ?? ''));
$supportPhone = post_str('support_phone', (string) ($existing['support_phone'] ?? ''));
$defaultTimezone = post_str('default_timezone', APP_TIMEZONE);
$defaultCurrency = post_str('default_currency', APP_CURRENCY);
$allowAutoLock = (int) post_str('allow_auto_lock', !empty($existing['allow_auto_lock']) ? '1' : '0') === 1;
$loginHeading = post_str('login_heading', (string) ($existing['login_heading'] ?? 'Sign In'));
$loginSubheading = post_str('login_subheading', (string) ($existing['login_subheading'] ?? 'Access your account'));
$loginCoverDescription = post_str('login_cover_description', (string) ($existing['login_cover_description'] ?? ''));
$loginTheme = post_str('login_theme', (string) ($existing['login_theme'] ?? 'earth'));
$loginAccountsEnabled = (int) post_str('login_accounts_enabled', !empty($existing['login_accounts_enabled']) ? '1' : '0') === 1;
$loginAccountsPayload = post_str('login_accounts_payload', (string) ($existing['login_accounts_payload'] ?? ''));

if ($saasName === '') {
    $saasName = APP_NAME;
}

if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Support email is not valid.');
    action_redirect_back('modules/settings/index.php');
}

if (!in_array($defaultTimezone, timezone_identifiers_list(), true)) {
    $defaultTimezone = APP_TIMEZONE;
}

if (!in_array($loginTheme, ['earth', 'ocean', 'mono'], true)) {
    $loginTheme = 'earth';
}

try {
    $systemLogoPath = (string) ($existing['system_logo_path'] ?? '');
    $coverImagePath = (string) ($existing['login_cover_image_path'] ?? '');

    $newLogo = store_platform_image('system_logo_file', 'system_logo', $assetDir);
    if ($newLogo !== null) {
        $systemLogoPath = $newLogo;
    }

    $newCover = store_platform_image('login_cover_file', 'login_cover', $assetDir);
    if ($newCover !== null) {
        $coverImagePath = $newCover;
    }

    $payload = [
        'saas_name' => $saasName,
        'footer_text' => $footerText,
        'support_email' => $supportEmail,
        'support_phone' => $supportPhone,
        'default_timezone' => $defaultTimezone,
        'default_currency' => $defaultCurrency,
        'allow_auto_lock' => $allowAutoLock,
        'login_heading' => $loginHeading,
        'login_subheading' => $loginSubheading,
        'login_cover_description' => $loginCoverDescription,
        'login_theme' => $loginTheme,
        'login_accounts_enabled' => $loginAccountsEnabled,
        'login_accounts_payload' => $loginAccountsPayload,
        'system_logo_path' => $systemLogoPath,
        'login_cover_image_path' => $coverImagePath,
        'updated_at' => now_sql(),
        'updated_by' => (int) auth_user()['id'],
    ];

    if (file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Could not save platform settings.');
    }

    audit_log('settings', 'edit', 'platform_settings', 1, null, $payload);
    flash('success', 'Platform settings updated.');
} catch (Throwable $exception) {
    flash('error', 'Could not update platform settings: ' . $exception->getMessage());
}

action_redirect_back('modules/settings/index.php');
