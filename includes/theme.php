<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function ensure_user_theme_settings_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    $sql = 'CREATE TABLE IF NOT EXISTS user_theme_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        active_theme_key VARCHAR(50) NOT NULL DEFAULT "brown_default",
        dark_mode_enabled TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_theme_settings_user (tenant_id, user_id),
        CONSTRAINT fk_user_theme_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_theme_settings_user FOREIGN KEY (user_id) REFERENCES tenant_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        db()->query($sql);
    } catch (Throwable $exception) {
    }
}

function theme_palettes(): array
{
    return [
        'brown_default' => ['bg' => '#141312', 'surface' => '#1c1b1a', 'text' => '#e6e2df', 'muted' => '#c7b8b2', 'primary' => '#e7bdb1'],
        'dark_terra' => ['bg' => '#0f1215', 'surface' => '#171c22', 'text' => '#dfe7ef', 'muted' => '#9fb0c0', 'primary' => '#d5a27f'],
        'blue_modern' => ['bg' => '#eef3fa', 'surface' => '#ffffff', 'text' => '#172433', 'muted' => '#607289', 'primary' => '#2f73c8'],
        'green_growth' => ['bg' => '#edf6f0', 'surface' => '#ffffff', 'text' => '#1b3324', 'muted' => '#5f7e69', 'primary' => '#2c8b57'],
        'gold_royal' => ['bg' => '#f6f2e8', 'surface' => '#fffdf7', 'text' => '#3a2f19', 'muted' => '#7d6d4e', 'primary' => '#b78a1e'],
        'maroon_classic' => ['bg' => '#f5eeee', 'surface' => '#ffffff', 'text' => '#3d1f24', 'muted' => '#87636a', 'primary' => '#8d2d43'],
        'purple_accent' => ['bg' => '#f2eff9', 'surface' => '#ffffff', 'text' => '#2e2147', 'muted' => '#72628f', 'primary' => '#6d4acb'],
        'gray_professional' => ['bg' => '#eef0f2', 'surface' => '#ffffff', 'text' => '#26313d', 'muted' => '#65717e', 'primary' => '#48637f'],
        'teal_fresh' => ['bg' => '#eaf7f6', 'surface' => '#ffffff', 'text' => '#153739', 'muted' => '#527f80', 'primary' => '#1f8b8d'],
        'light_modern' => ['bg' => '#f7f8fb', 'surface' => '#ffffff', 'text' => '#1f2430', 'muted' => '#697181', 'primary' => '#4f6bd7'],
    ];
}

function current_user_theme_settings(): array
{
    $user = auth_user();
    if (!$user || auth_role() !== 'tenant_user') {
        return ['theme_key' => 'brown_default', 'dark_mode_enabled' => 0];
    }

    $tenantId = (int) ($user['tenant_id'] ?? 0);
    $userId = (int) ($user['id'] ?? 0);
    if ($tenantId <= 0 || $userId <= 0) {
        return ['theme_key' => 'brown_default', 'dark_mode_enabled' => 0];
    }

    ensure_user_theme_settings_table();

    $stmt = db()->prepare('SELECT active_theme_key, dark_mode_enabled FROM user_theme_settings WHERE tenant_id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        return ['theme_key' => 'brown_default', 'dark_mode_enabled' => 0];
    }
    $stmt->bind_param('ii', $tenantId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'theme_key' => (string) ($row['active_theme_key'] ?? 'brown_default'),
        'dark_mode_enabled' => (int) ($row['dark_mode_enabled'] ?? 0),
    ];
}

function current_theme_tokens(): array
{
    $settings = current_user_theme_settings();
    $palettes = theme_palettes();
    $key = (string) ($settings['theme_key'] ?? 'brown_default');
    $dark = (int) ($settings['dark_mode_enabled'] ?? 0) === 1;
    $base = $palettes[$key] ?? $palettes['brown_default'];

    $tokens = [
        'bg' => $base['bg'],
        'surface' => $base['surface'],
        'surface_soft' => $base['surface'] === '#ffffff' ? 'rgba(255, 255, 255, 0.85)' : 'rgba(32, 31, 30, 0.72)',
        'surface_strong' => $base['surface'] === '#ffffff' ? 'rgba(248, 250, 252, 0.88)' : 'rgba(54, 52, 51, 0.7)',
        'text' => $base['text'],
        'muted' => $base['muted'],
        'primary' => $base['primary'],
        'primary_strong' => $base['primary'],
        'on_primary' => $base['surface'] === '#ffffff' ? '#ffffff' : '#2b1c17',
        'danger' => '#d94b4b',
        'success' => '#289a57',
        'outline' => $base['surface'] === '#ffffff' ? 'rgba(30, 42, 56, 0.14)' : 'rgba(156, 141, 137, 0.2)',
        'sidebar_bg' => $base['surface'] === '#ffffff' ? 'rgba(255, 255, 255, 0.94)' : 'rgba(20, 19, 18, 0.8)',
        'sidebar_flyout_bg' => $base['surface'] === '#ffffff' ? 'rgba(255, 255, 255, 0.98)' : 'rgba(20, 19, 18, 0.96)',
        'input_bg' => $base['surface'] === '#ffffff' ? '#ffffff' : 'rgba(15, 14, 13, 0.7)',
    ];

    if ($dark) {
        $tokens['bg'] = '#0f1115';
        $tokens['surface'] = '#171b22';
        $tokens['surface_soft'] = 'rgba(25, 29, 37, 0.86)';
        $tokens['surface_strong'] = 'rgba(37, 44, 55, 0.85)';
        $tokens['text'] = '#e6edf5';
        $tokens['muted'] = '#b8c4d0';
        $tokens['outline'] = 'rgba(154, 170, 188, 0.22)';
        $tokens['danger'] = '#ff8f84';
        $tokens['success'] = '#7ddca0';
        $tokens['on_primary'] = '#0f141a';
        $tokens['sidebar_bg'] = 'rgba(16, 20, 27, 0.92)';
        $tokens['sidebar_flyout_bg'] = 'rgba(16, 20, 27, 0.98)';
        $tokens['input_bg'] = 'rgba(18, 22, 29, 0.92)';
    }

    return $tokens;
}
