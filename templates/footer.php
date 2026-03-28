<?php $footerText = (string) platform_setting('footer_text', ''); ?>
<?php if (empty($hideAppFooter) && trim($footerText) !== ''): ?>
<footer class="app-footer"><?php echo nl2br(e($footerText)); ?></footer>
<?php endif; ?>
<script src="<?php echo e(app_url('assets/js/app.js')); ?>"></script>
</body>
</html>
