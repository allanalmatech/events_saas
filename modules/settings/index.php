<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Settings';
$moduleKey = 'settings';
$modulePermission = 'settings.view';
$moduleDescription = 'Business branding, theme preferences, contact details, and operational defaults.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $isSuperAdmin = !empty((auth_user() ?: [])['is_super_admin']);
    $settings = null;
    $tenantProfile = null;
    $userThemeSettings = null;
    $themes = [];
    $isDirector = auth_role() === 'director';

    if ($isDirector) {
        $platform = platform_settings();
        $tzOptions = supported_timezones();
        $systemLogoPath = trim((string) ($platform['system_logo_path'] ?? ''));
        $coverPath = trim((string) ($platform['login_cover_image_path'] ?? ''));
        ?>
        <style>
            .settings-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
            .settings-tab-btn.active { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 18%, transparent); }
            .settings-panel { display:none; }
            .settings-panel.active { display:block; }
            .theme-preview-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px; margin-top:10px; }
            .theme-preview { border:1px solid var(--outline); border-radius:10px; overflow:hidden; background:var(--surface); }
            .theme-preview .swatch { height:52px; }
            .theme-preview .meta { padding:8px; font-size:12px; display:flex; justify-content:space-between; align-items:center; }
            .theme-preview.active { border-color: var(--primary); box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 35%, transparent) inset; }
            @media (max-width: 760px) { .theme-preview-grid { grid-template-columns:1fr; } }
        </style>

        <section>
            <div class="settings-tabs">
                <button class="btn btn-ghost settings-tab-btn" type="button" data-settings-tab="platform-core">Platform</button>
                <button class="btn btn-ghost settings-tab-btn" type="button" data-settings-tab="platform-login">Login Screen</button>
            </div>

            <article class="card settings-panel" data-settings-panel="platform-core">
                <h3 style="margin-top:0;">Platform Settings</h3>
                <form method="post" action="<?php echo e(app_url('actions/update_platform_settings.php')); ?>" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <div class="field"><label>SaaS Name</label><input name="saas_name" value="<?php echo e($platform['saas_name']); ?>" required></div>
                    <div class="field"><label>Footer Text</label><textarea name="footer_text" placeholder="Shown exactly as entered in app footer"><?php echo e($platform['footer_text']); ?></textarea></div>
                    <div class="field"><label>Support Email</label><input type="email" name="support_email" value="<?php echo e($platform['support_email']); ?>"></div>
                    <div class="field"><label>Support Phone</label><input name="support_phone" value="<?php echo e($platform['support_phone']); ?>"></div>
                    <div class="field"><label>Default Timezone</label><select name="default_timezone"><?php foreach ($tzOptions as $tz): ?><option value="<?php echo e($tz); ?>" <?php echo (($platform['default_timezone'] ?? APP_TIMEZONE) === $tz) ? 'selected' : ''; ?>><?php echo e($tz); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label>Default Currency</label><input name="default_currency" value="<?php echo e($platform['default_currency']); ?>"></div>
                    <div class="field"><label>Allow Auto-Lock</label><select name="allow_auto_lock"><option value="1" <?php echo !empty($platform['allow_auto_lock']) ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo empty($platform['allow_auto_lock']) ? 'selected' : ''; ?>>No</option></select></div>
                    <button class="btn btn-primary" type="submit">Save Platform Settings</button>
                </form>
            </article>

            <article class="card settings-panel" data-settings-panel="platform-login">
                <h3 style="margin-top:0;">Login Screen</h3>
                <form method="post" action="<?php echo e(app_url('actions/update_platform_settings.php')); ?>" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <div class="field"><label>Login Heading</label><input name="login_heading" value="<?php echo e($platform['login_heading']); ?>"></div>
                    <div class="field"><label>Login Subheading</label><input name="login_subheading" value="<?php echo e($platform['login_subheading']); ?>"></div>
                    <div class="field"><label>Cover Description</label><textarea name="login_cover_description" placeholder="Shown on the cover image side"><?php echo e($platform['login_cover_description'] ?? ''); ?></textarea></div>
                    <div class="field"><label>Login Theme</label><select name="login_theme" id="login-theme-select"><option value="earth" <?php echo ($platform['login_theme'] ?? '') === 'earth' ? 'selected' : ''; ?>>Earth</option><option value="ocean" <?php echo ($platform['login_theme'] ?? '') === 'ocean' ? 'selected' : ''; ?>>Ocean</option><option value="mono" <?php echo ($platform['login_theme'] ?? '') === 'mono' ? 'selected' : ''; ?>>Mono</option></select></div>
                    <div class="field"><label>Show Login Quick Accounts</label><select name="login_accounts_enabled"><option value="0" <?php echo empty($platform['login_accounts_enabled']) ? 'selected' : ''; ?>>Disabled</option><option value="1" <?php echo !empty($platform['login_accounts_enabled']) ? 'selected' : ''; ?>>Enabled</option></select></div>
                    <div class="field"><label>Quick Accounts</label><textarea name="login_accounts_payload" placeholder="One account per line: Label|email@example.com|password"><?php echo e($platform['login_accounts_payload'] ?? ''); ?></textarea></div>
                    <p class="muted" style="margin-top:-6px;">Format each line as <code>Label|email|password</code>. Example: <code>Manager|manager@example.com|Pass1234</code></p>
                    <div class="theme-preview-grid" id="login-theme-preview-grid">
                        <div class="theme-preview" data-theme-preview="earth">
                            <div class="swatch" style="background:linear-gradient(135deg,#b77753,#5f3f2f);"></div>
                            <div class="meta"><span>Earth</span><span>Warm</span></div>
                        </div>
                        <div class="theme-preview" data-theme-preview="ocean">
                            <div class="swatch" style="background:linear-gradient(135deg,#2c7fba,#0f3d5c);"></div>
                            <div class="meta"><span>Ocean</span><span>Cool</span></div>
                        </div>
                        <div class="theme-preview" data-theme-preview="mono">
                            <div class="swatch" style="background:linear-gradient(135deg,#5a6470,#1a2028);"></div>
                            <div class="meta"><span>Mono</span><span>Neutral</span></div>
                        </div>
                    </div>
                    <div class="field"><label>System Logo</label><input type="file" name="system_logo_file" accept="image/png,image/jpeg,image/webp,image/gif"></div>
                    <?php if ($systemLogoPath !== ''): ?><div class="field"><label>Current System Logo</label><img src="<?php echo e(app_url(ltrim($systemLogoPath, '/'))); ?>" alt="System logo" style="max-height:50px; border:1px solid var(--outline); border-radius:8px;"></div><?php endif; ?>
                    <div class="field"><label>Login Cover Image</label><input type="file" name="login_cover_file" accept="image/png,image/jpeg,image/webp,image/gif"></div>
                    <?php if ($coverPath !== ''): ?><div class="field"><label>Current Cover Image</label><img src="<?php echo e(app_url(ltrim($coverPath, '/'))); ?>" alt="Login cover" style="max-width:100%; max-height:130px; border:1px solid var(--outline); border-radius:8px;"></div><?php endif; ?>
                    <button class="btn btn-primary" type="submit">Save Login Settings</button>
                </form>
            </article>
        </section>

        <script>
            (function () {
                var tabs = document.querySelectorAll('[data-settings-tab]');
                var panels = document.querySelectorAll('[data-settings-panel]');
                if (!tabs.length || !panels.length) {
                    return;
                }
                function activate(tabKey) {
                    for (var i = 0; i < tabs.length; i++) {
                        tabs[i].classList.toggle('active', tabs[i].getAttribute('data-settings-tab') === tabKey);
                    }
                    for (var j = 0; j < panels.length; j++) {
                        panels[j].classList.toggle('active', panels[j].getAttribute('data-settings-panel') === tabKey);
                    }
                }
                for (var k = 0; k < tabs.length; k++) {
                    tabs[k].addEventListener('click', function () {
                        activate(this.getAttribute('data-settings-tab'));
                    });
                }
                activate(tabs[0].getAttribute('data-settings-tab'));

                var loginThemeSelect = document.getElementById('login-theme-select');
                var previewCards = document.querySelectorAll('[data-theme-preview]');
                function highlightThemeCard(value) {
                    for (var p = 0; p < previewCards.length; p++) {
                        previewCards[p].classList.toggle('active', previewCards[p].getAttribute('data-theme-preview') === value);
                    }
                }
                if (loginThemeSelect && previewCards.length) {
                    highlightThemeCard(loginThemeSelect.value);
                    loginThemeSelect.addEventListener('change', function () {
                        highlightThemeCard(loginThemeSelect.value);
                    });
                    for (var q = 0; q < previewCards.length; q++) {
                        previewCards[q].addEventListener('click', function () {
                            var chosen = this.getAttribute('data-theme-preview');
                            loginThemeSelect.value = chosen;
                            highlightThemeCard(chosen);
                        });
                    }
                }
            })();
        </script>
        <?php
        return;
    }

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $tenantStmt = $mysqli->prepare('SELECT business_name, tagline, email, phone, timezone, address, logo_path FROM tenants WHERE id = ? LIMIT 1');
        if ($tenantStmt) {
            $tenantStmt->bind_param('i', $tenantId);
            $tenantStmt->execute();
            $tenantProfile = $tenantStmt->get_result()->fetch_assoc();
            $tenantStmt->close();
        }

        $tzOptions = supported_timezones();

        $stmt = $mysqli->prepare('SELECT invoice_footer, receipt_footer, default_tax_percent FROM tenant_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        ensure_user_theme_settings_table();
        $themeStmt = $mysqli->prepare('SELECT active_theme_key, dark_mode_enabled FROM user_theme_settings WHERE tenant_id = ? AND user_id = ? LIMIT 1');
        if ($themeStmt) {
            $userId = (int) (auth_user()['id'] ?? 0);
            $themeStmt->bind_param('ii', $tenantId, $userId);
            $themeStmt->execute();
            $userThemeSettings = $themeStmt->get_result()->fetch_assoc();
            $themeStmt->close();
        }

        $themeQ = $mysqli->query('SELECT theme_key, theme_name FROM system_themes ORDER BY sort_order ASC');
        $themes = $themeQ ? $themeQ->fetch_all(MYSQLI_ASSOC) : [];
    }
    ?>
    <style>
        .settings-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
        .settings-tab-btn.active { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 18%, transparent); }
        .settings-panel { display:none; }
        .settings-panel.active { display:block; }
    </style>

    <section>
        <div class="settings-tabs">
            <button class="btn btn-ghost settings-tab-btn" type="button" data-settings-tab="theme">Theme</button>
            <?php if ($isSuperAdmin): ?>
                <button class="btn btn-ghost settings-tab-btn" type="button" data-settings-tab="branding">Business Branding</button>
                <button class="btn btn-ghost settings-tab-btn" type="button" data-settings-tab="documents">Documents</button>
            <?php endif; ?>
        </div>

        <article class="card settings-panel" data-settings-panel="theme">
            <h3 style="margin-top:0;">Theme</h3>
            <form method="post" action="<?php echo e(app_url('actions/change_theme.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Theme</label><select name="theme_key"><?php foreach ($themes as $theme): ?><option value="<?php echo e($theme['theme_key']); ?>" <?php echo (($userThemeSettings['active_theme_key'] ?? 'brown_default') === $theme['theme_key']) ? 'selected' : ''; ?>><?php echo e($theme['theme_name']); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Dark Mode</label><select name="dark_mode_enabled"><option value="0" <?php echo ((int) ($userThemeSettings['dark_mode_enabled'] ?? 0) === 0) ? 'selected' : ''; ?>>Disabled</option><option value="1" <?php echo ((int) ($userThemeSettings['dark_mode_enabled'] ?? 0) === 1) ? 'selected' : ''; ?>>Enabled</option></select></div>
                <button class="btn btn-primary" type="submit">Save Theme</button>
            </form>
        </article>

        <?php if ($isSuperAdmin): ?>
        <article class="card settings-panel" data-settings-panel="branding">
            <h3 style="margin-top:0;">Business Branding</h3>
            <form method="post" action="<?php echo e(app_url('actions/update_tenant_branding.php')); ?>" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Business Name</label><input name="business_name" value="<?php echo e($tenantProfile['business_name'] ?? ''); ?>" required></div>
                <div class="field"><label>Business Description</label><textarea name="business_description" placeholder="Short description shown on receipts"><?php echo e($tenantProfile['tagline'] ?? ''); ?></textarea></div>
                <div class="field"><label>Business Email</label><input type="email" name="business_email" value="<?php echo e($tenantProfile['email'] ?? ''); ?>" required></div>
                <div class="field"><label>Business Phone</label><input name="business_phone" value="<?php echo e($tenantProfile['phone'] ?? ''); ?>"></div>
                <div class="field"><label>Business Timezone</label><select name="business_timezone"><?php foreach (($tzOptions ?? supported_timezones()) as $tz): ?><option value="<?php echo e($tz); ?>" <?php echo (($tenantProfile['timezone'] ?? APP_TIMEZONE) === $tz) ? 'selected' : ''; ?>><?php echo e($tz); ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Business Address / Location</label><textarea name="business_address"><?php echo e($tenantProfile['address'] ?? ''); ?></textarea></div>
                <div class="field"><label>Logo</label><input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif"></div>
                <?php if (!empty($tenantProfile['logo_path'])): ?>
                    <div class="field"><label>Current Logo</label><img src="<?php echo e(app_url(ltrim((string) $tenantProfile['logo_path'], '/'))); ?>" alt="Business logo" style="max-height:54px; width:auto; border:1px solid var(--outline); border-radius:8px; background:var(--surface);"></div>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit">Save Branding</button>
            </form>
        </article>

        <article class="card settings-panel" data-settings-panel="documents">
            <h3 style="margin-top:0;">Business Document Settings</h3>
            <form method="post" action="<?php echo e(app_url('actions/update_settings.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Invoice Footer</label><textarea name="invoice_footer"><?php echo e($settings['invoice_footer'] ?? ''); ?></textarea></div>
                <div class="field"><label>Receipt Footer</label><textarea name="receipt_footer"><?php echo e($settings['receipt_footer'] ?? ''); ?></textarea></div>
                <div class="field"><label>Default Tax %</label><input type="number" step="0.01" min="0" name="default_tax_percent" value="<?php echo e((string) ($settings['default_tax_percent'] ?? '0')); ?>"></div>
                <button class="btn btn-primary" type="submit">Save Settings</button>
            </form>
        </article>
        <?php endif; ?>
    </section>

    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-settings-tab]');
            var panels = document.querySelectorAll('[data-settings-panel]');
            if (!tabs.length || !panels.length) {
                return;
            }

            function activate(tabKey) {
                for (var i = 0; i < tabs.length; i++) {
                    tabs[i].classList.toggle('active', tabs[i].getAttribute('data-settings-tab') === tabKey);
                }
                for (var j = 0; j < panels.length; j++) {
                    panels[j].classList.toggle('active', panels[j].getAttribute('data-settings-panel') === tabKey);
                }
            }

            for (var k = 0; k < tabs.length; k++) {
                tabs[k].addEventListener('click', function () {
                    activate(this.getAttribute('data-settings-tab'));
                });
            }

            activate(tabs[0].getAttribute('data-settings-tab'));
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
