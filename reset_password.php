<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Set New Password';
$error = flash('error');
$success = flash('success');

include __DIR__ . '/templates/header.php';
?>
<main class="auth-wrap">
    <section class="glass auth-card">
        <h1 style="margin-top:0;">Set New Password</h1>
        <?php if ($error): ?><div class="alert error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?php echo e($success); ?></div><?php endif; ?>
        <form method="post" action="<?php echo e(app_url('actions/reset_password.php')); ?>">
            <?php echo csrf_input(); ?>
            <div class="field"><label>Reset Token</label><input name="token" required></div>
            <div class="field"><label>New Password</label><input type="password" name="password" minlength="8" required></div>
            <button class="btn btn-primary" type="submit">Update Password</button>
            <a class="btn btn-ghost" href="<?php echo e(app_url('login.php')); ?>">Back to Login</a>
        </form>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
