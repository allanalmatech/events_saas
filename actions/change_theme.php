<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();

$tenantId = (int) current_tenant_id();
$userId = (int) (auth_user()['id'] ?? 0);
$themeKey = post_str('theme_key', 'brown_default');
$darkMode = (int) post_str('dark_mode_enabled', '0');

$mysqli = db();
$stmt = $mysqli->prepare('SELECT id FROM system_themes WHERE theme_key = ? LIMIT 1');
$stmt->bind_param('s', $themeKey);
$stmt->execute();
$found = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$found) {
    flash('error', 'Theme not found.');
    action_redirect_back('modules/settings/index.php');
}

if ($userId <= 0) {
    flash('error', 'User session is invalid.');
    action_redirect_back('modules/settings/index.php');
}

ensure_user_theme_settings_table();

$stmt = $mysqli->prepare('INSERT INTO user_theme_settings (tenant_id, user_id, active_theme_key, dark_mode_enabled, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE active_theme_key = VALUES(active_theme_key), dark_mode_enabled = VALUES(dark_mode_enabled), updated_at = NOW()');
$stmt->bind_param('iisi', $tenantId, $userId, $themeKey, $darkMode);
$stmt->execute();
$stmt->close();

audit_log('settings', 'theme_change', 'user_theme_settings', $userId, null, ['theme_key' => $themeKey, 'dark_mode' => $darkMode]);
flash('success', 'Theme updated.');
action_redirect_back('modules/settings/index.php');
