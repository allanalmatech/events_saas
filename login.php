<?php
require_once __DIR__ . '/includes/functions.php';

if (auth_check()) {
    redirect('dashboard.php');
}

$pageTitle = 'Login';
$error = flash('error');
$success = flash('success');
$platform = platform_settings();

$loginHeading = trim((string) ($platform['login_heading'] ?? 'Sign In'));
if ($loginHeading === '') {
    $loginHeading = 'Sign In';
}
$loginSubheading = trim((string) ($platform['login_subheading'] ?? 'Access your account'));
$coverDescription = trim((string) ($platform['login_cover_description'] ?? ''));
$footerText = trim((string) ($platform['footer_text'] ?? ''));
$saasName = platform_saas_name();
$loginTheme = (string) ($platform['login_theme'] ?? 'earth');
if (!in_array($loginTheme, ['earth', 'ocean', 'mono'], true)) {
    $loginTheme = 'earth';
}

$logoPath = trim((string) ($platform['system_logo_path'] ?? ''));
$coverPath = trim((string) ($platform['login_cover_image_path'] ?? ''));
$logoUrl = $logoPath !== '' ? app_url(ltrim($logoPath, '/')) : '';
$coverUrl = $coverPath !== '' ? app_url(ltrim($coverPath, '/')) : '';

$themeMap = [
    'earth' => [
        'accent' => '#b77753',
        'coverOverlay' => 'rgba(18, 12, 8, 0.46)',
        'cardBg' => 'rgba(255, 249, 244, 0.9)',
        'cardText' => '#2b201a',
        'cardMuted' => '#7f6354',
        'fieldBg' => '#fff7f2',
        'fieldBorder' => '#d9c0b0',
    ],
    'ocean' => [
        'accent' => '#2c7fba',
        'coverOverlay' => 'rgba(9, 27, 41, 0.52)',
        'cardBg' => 'rgba(242, 250, 255, 0.92)',
        'cardText' => '#113148',
        'cardMuted' => '#4d6d82',
        'fieldBg' => '#f3fbff',
        'fieldBorder' => '#b9d8ea',
    ],
    'mono' => [
        'accent' => '#5a6470',
        'coverOverlay' => 'rgba(16, 18, 23, 0.58)',
        'cardBg' => 'rgba(246, 247, 249, 0.94)',
        'cardText' => '#1b2028',
        'cardMuted' => '#5f6875',
        'fieldBg' => '#ffffff',
        'fieldBorder' => '#c9d0d8',
    ],
];
$themeUi = $themeMap[$loginTheme];

include __DIR__ . '/templates/header.php';
?>
<style>
    .login-shell { height:100vh; display:grid; grid-template-columns: minmax(320px, 460px) 1fr; overflow:hidden; }
    .login-panel { padding:28px; display:flex; align-items:center; justify-content:center; overflow-y:auto; }
    .login-panel-stack { width:100%; max-width:380px; display:flex; flex-direction:column; gap:10px; }
    .login-card { width:100%; border:1px solid <?php echo e($themeUi['fieldBorder']); ?>; border-radius:18px; background:<?php echo e($themeUi['cardBg']); ?>; padding:20px; color:<?php echo e($themeUi['cardText']); ?>; }
    .login-panel-footer { font-size:12px; color:<?php echo e($themeUi['cardMuted']); ?>; text-align:center; white-space:pre-wrap; }
    .login-card .muted { color: <?php echo e($themeUi['cardMuted']); ?>; }
    .login-card .field label { color: <?php echo e($themeUi['cardMuted']); ?>; }
    .login-card .field input { background: <?php echo e($themeUi['fieldBg']); ?>; border:1px solid <?php echo e($themeUi['fieldBorder']); ?>; color:<?php echo e($themeUi['cardText']); ?>; }
    .login-card .field input:focus { border-color: <?php echo e($themeUi['accent']); ?>; box-shadow: 0 0 0 3px color-mix(in srgb, <?php echo e($themeUi['accent']); ?> 22%, transparent); }
    .login-card .btn-primary { background: linear-gradient(135deg, <?php echo e($themeUi['accent']); ?>, color-mix(in srgb, <?php echo e($themeUi['accent']); ?> 78%, #000)); color:#fff; }
    .login-card .btn-ghost { border-color: <?php echo e($themeUi['fieldBorder']); ?>; color:<?php echo e($themeUi['cardText']); ?>; }
    .login-brand { display:flex; gap:10px; align-items:center; margin-bottom:10px; }
    .login-logo { width:44px; height:44px; border-radius:10px; border:1px solid var(--outline); object-fit:cover; background:#fff; }
    .login-cover {
        background:
            linear-gradient(135deg, <?php echo e($themeUi['coverOverlay']); ?>, transparent 70%),
            url('<?php echo e($coverUrl); ?>') center/cover no-repeat;
        position:relative;
        min-height:100vh;
    }
    .login-cover::before {
        content:"";
        position:absolute;
        inset:0;
        background: radial-gradient(circle at 85% 20%, color-mix(in srgb, <?php echo e($themeUi['accent']); ?> 35%, transparent), transparent 48%);
    }
    .login-cta {
        position:absolute;
        left:24px;
        bottom:24px;
        color:#fff;
        z-index:1;
        text-shadow:0 1px 2px rgba(0,0,0,0.4);
        max-width:78%;
        background:rgba(10, 14, 20, 0.32);
        border:1px solid rgba(255,255,255,0.28);
        backdrop-filter: blur(8px);
        border-radius:16px;
        padding:12px 14px;
    }
    .login-cover-desc { margin:6px 0 0; color:rgba(255,255,255,0.92); font-size:14px; line-height:1.45; white-space:pre-wrap; }
    @media (max-width: 980px) {
        .login-shell { height:auto; min-height:100vh; grid-template-columns: 1fr; overflow:visible; }
        .login-cover { min-height:190px; order:-1; }
        .login-panel { overflow:visible; }
    }
</style>

<main class="login-shell">
    <aside class="login-cover">
        <div class="login-cta">
            <h2 style="margin:0;"><?php echo e($saasName); ?></h2>
            <?php if ($coverDescription !== ''): ?><p class="login-cover-desc"><?php echo e($coverDescription); ?></p><?php endif; ?>
        </div>
    </aside>

    <section class="login-panel">
        <div class="login-panel-stack">
            <div class="login-card">
                <div class="login-brand">
                    <?php if ($logoUrl !== ''): ?><img src="<?php echo e($logoUrl); ?>" alt="System logo" class="login-logo"><?php endif; ?>
                    <strong style="font-size:18px;"><?php echo e($saasName); ?></strong>
                </div>

                <h1 style="margin:0 0 6px;"><?php echo e($loginHeading); ?></h1>
                <p class="muted" style="margin-top:0;"><?php echo e($loginSubheading); ?></p>

                <?php if ($error): ?><div class="alert error"><?php echo e($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert success"><?php echo e($success); ?></div><?php endif; ?>

                <form method="post" action="<?php echo e(app_url('actions/login.php')); ?>">
                    <?php echo csrf_input(); ?>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" required>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required>
                    </div>
                    <p><a class="btn btn-ghost" href="<?php echo e(app_url('forgot_password.php')); ?>">Forgot Password?</a></p>
                    <button class="btn btn-primary" type="submit">Sign In</button>
                    <a class="btn btn-ghost" href="<?php echo e(app_url('register.php')); ?>">Request Tenant Access</a>
                </form>
            </div>
            <?php if ($footerText !== ''): ?><div class="login-panel-footer"><?php echo nl2br(e($footerText)); ?></div><?php endif; ?>
        </div>
    </section>
</main>
<?php $hideAppFooter = true; ?>
<?php include __DIR__ . '/templates/footer.php'; ?>
