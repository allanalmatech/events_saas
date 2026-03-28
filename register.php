<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Tenant Signup';
$error = flash('error');
$success = flash('success');
$tzOptions = supported_timezones();

include __DIR__ . '/templates/header.php';
?>
<main class="auth-wrap">
    <section class="glass auth-card">
        <h1 style="margin-top:0;">Business Signup</h1>
        <p class="muted">Create tenant request. Director must approve before access.</p>

        <?php if ($error): ?><div class="alert error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?php echo e($success); ?></div><?php endif; ?>

        <form method="post" action="<?php echo e(app_url('actions/register.php')); ?>">
            <?php echo csrf_input(); ?>
            <div class="field">
                <label for="business_name">Business Name</label>
                <input id="business_name" name="business_name" required>
            </div>
            <div class="field">
                <label for="business_email">Business Email</label>
                <input id="business_email" name="business_email" type="email" required>
            </div>
            <div class="field">
                <label for="business_phone">Business Phone</label>
                <input id="business_phone" name="business_phone" required>
            </div>
            <div class="field">
                <label for="business_timezone">Business Timezone</label>
                <select id="business_timezone" name="business_timezone">
                    <?php foreach ($tzOptions as $tz): ?><option value="<?php echo e($tz); ?>" <?php echo $tz === APP_TIMEZONE ? 'selected' : ''; ?>><?php echo e($tz); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="owner_name">Owner Full Name</label>
                <input id="owner_name" name="owner_name" required>
            </div>
            <div class="field">
                <label for="owner_email">Owner Email</label>
                <input id="owner_email" name="owner_email" type="email" required>
            </div>
            <div class="field">
                <label for="owner_password">Owner Password</label>
                <input id="owner_password" name="owner_password" type="password" minlength="8" required>
            </div>
            <button class="btn btn-primary" type="submit">Submit Request</button>
            <a class="btn btn-ghost" href="<?php echo e(app_url('login.php')); ?>">Back to Login</a>
        </form>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
