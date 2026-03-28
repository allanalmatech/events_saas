<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Forgot Password';
$error = flash('error');
$success = flash('success');

include __DIR__ . '/templates/header.php';
?>
<main class="auth-wrap">
    <section class="glass auth-card">
        <h1 style="margin-top:0;">Reset Password</h1>
        <p class="muted">Request a temporary reset token for Director or Tenant/Staff account.</p>
        <?php if ($error): ?><div class="alert error"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?php echo e($success); ?></div><?php endif; ?>
        <form method="post" action="<?php echo e(app_url('actions/request_password_reset.php')); ?>">
            <?php echo csrf_input(); ?>
            <div class="field"><label>Role</label><select name="role_type"><option value="tenant_user">Tenant / Staff</option><option value="director">Director</option></select></div>
            <div class="field"><label>Email</label><input type="email" name="email" required></div>
            <button class="btn btn-primary" type="submit">Request Token</button>
            <a class="btn btn-ghost" href="<?php echo e(app_url('reset_password.php')); ?>">I have a token</a>
        </form>
    </section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
