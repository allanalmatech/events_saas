<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('settings.edit');

if (empty((auth_user() ?: [])['is_super_admin'])) {
    flash('error', 'Only Super Users can update business branding.');
    action_redirect_back('modules/settings/index.php');
}

$tenantId = (int) current_tenant_id();
$businessName = post_str('business_name');
$businessDescription = post_str('business_description');
$businessEmail = post_str('business_email');
$businessPhone = post_str('business_phone');
$businessTimezone = post_str('business_timezone', APP_TIMEZONE);
$businessAddress = post_str('business_address');

if ($tenantId <= 0 || $businessName === '' || $businessEmail === '') {
    flash('error', 'Business name and email are required.');
    action_redirect_back('modules/settings/index.php');
}

if (!filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Enter a valid business email address.');
    action_redirect_back('modules/settings/index.php');
}

if (!in_array($businessTimezone, timezone_identifiers_list(), true)) {
    $businessTimezone = APP_TIMEZONE;
}

$mysqli = db();
$existingLogoPath = '';

$existingStmt = $mysqli->prepare('SELECT logo_path FROM tenants WHERE id = ? LIMIT 1');
if ($existingStmt) {
    $existingStmt->bind_param('i', $tenantId);
    $existingStmt->execute();
    $row = $existingStmt->get_result()->fetch_assoc();
    $existingLogoPath = (string) ($row['logo_path'] ?? '');
    $existingStmt->close();
}

$logoPath = $existingLogoPath;

if (isset($_FILES['logo_file']) && is_array($_FILES['logo_file']) && (int) ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $error = (int) ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        flash('error', 'Logo upload failed.');
        action_redirect_back('modules/settings/index.php');
    }

    $tmpName = (string) ($_FILES['logo_file']['tmp_name'] ?? '');
    $size = (int) ($_FILES['logo_file']['size'] ?? 0);
    if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0) {
        flash('error', 'Invalid logo file.');
        action_redirect_back('modules/settings/index.php');
    }
    if ($size > 5 * 1024 * 1024) {
        flash('error', 'Logo file is too large (max 5MB).');
        action_redirect_back('modules/settings/index.php');
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
        flash('error', 'Unsupported logo format. Use JPG, PNG, WEBP, or GIF.');
        action_redirect_back('modules/settings/index.php');
    }

    $dir = __DIR__ . '/../storage/tenant_logos';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        flash('error', 'Could not prepare logo storage folder.');
        action_redirect_back('modules/settings/index.php');
    }

    $filename = 'tenant_' . $tenantId . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extMap[$mime];
    $target = $dir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $target)) {
        flash('error', 'Could not save uploaded logo.');
        action_redirect_back('modules/settings/index.php');
    }

    $logoPath = 'storage/tenant_logos/' . $filename;
}

$stmt = $mysqli->prepare('UPDATE tenants SET business_name = ?, tagline = ?, email = ?, phone = ?, timezone = ?, address = ?, logo_path = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
$stmt->bind_param('sssssssi', $businessName, $businessDescription, $businessEmail, $businessPhone, $businessTimezone, $businessAddress, $logoPath, $tenantId);
$stmt->execute();
$stmt->close();

if (!empty($_SESSION['auth_user']) && (int) ($_SESSION['auth_user']['tenant_id'] ?? 0) === $tenantId) {
    $_SESSION['auth_user']['timezone'] = $businessTimezone;
}

audit_log('settings', 'branding_update', 'tenants', $tenantId, null, ['business_name' => $businessName, 'tagline' => $businessDescription, 'email' => $businessEmail, 'phone' => $businessPhone, 'timezone' => $businessTimezone, 'logo_path' => $logoPath]);
flash('success', 'Business branding updated.');
action_redirect_back('modules/settings/index.php');
