<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Marketplace';
$moduleKey = 'marketplace';
$modulePermission = 'marketplace.view';
$moduleDescription = 'Publish tenant services/catalogue and discover community collaboration opportunities.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $rows = [];
    $profile = null;

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $p = $mysqli->prepare('SELECT public_name, about_text, contact_email, contact_phone, location_text, is_public FROM marketplace_profiles WHERE tenant_id = ? LIMIT 1');
        $p->bind_param('i', $tenantId);
        $p->execute();
        $profile = $p->get_result()->fetch_assoc();
        $p->close();

        $q = $mysqli->prepare('SELECT title, listing_type, availability_status, is_active, created_at FROM marketplace_catalogue WHERE tenant_id = ? ORDER BY id DESC LIMIT 40');
        $q->bind_param('i', $tenantId);
        $q->execute();
        $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
        $q->close();
    }
    ?>
    <section class="grid cols-2" style="margin-bottom:14px;">
        <article class="card">
            <h3 style="margin-top:0;">Marketplace Profile</h3>
            <form method="post" action="<?php echo e(app_url('actions/save_marketplace_profile.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Public Name</label><input name="public_name" value="<?php echo e($profile['public_name'] ?? ''); ?>" required></div>
                <div class="field"><label>About</label><textarea name="about_text"><?php echo e($profile['about_text'] ?? ''); ?></textarea></div>
                <div class="field"><label>Contact Email</label><input name="contact_email" type="email" value="<?php echo e($profile['contact_email'] ?? ''); ?>"></div>
                <div class="field"><label>Contact Phone</label><input name="contact_phone" value="<?php echo e($profile['contact_phone'] ?? ''); ?>"></div>
                <div class="field"><label>Location</label><input name="location_text" value="<?php echo e($profile['location_text'] ?? ''); ?>"></div>
                <div class="field"><label>Visibility</label><select name="is_public"><option value="1" <?php echo isset($profile['is_public']) && (int) $profile['is_public'] === 1 ? 'selected' : ''; ?>>Public</option><option value="0" <?php echo isset($profile['is_public']) && (int) $profile['is_public'] === 0 ? 'selected' : ''; ?>>Private</option></select></div>
                <button class="btn btn-primary" type="submit">Save Profile</button>
            </form>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Publish Listing</h3>
            <form method="post" action="<?php echo e(app_url('actions/save_marketplace_listing.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Title</label><input name="title" required></div>
                <div class="field"><label>Type</label><select name="listing_type"><option value="service">Service</option><option value="item">Item</option></select></div>
                <div class="field"><label>Description</label><textarea name="description"></textarea></div>
                <div class="field"><label>Availability</label><input name="availability_status" value="Available"></div>
                <button class="btn btn-primary" type="submit">Publish</button>
            </form>
        </article>
    </section>

    <section class="card">
        <h3 style="margin-top:0;">My Listings</h3>
        <table class="table">
            <thead><tr><th>Title</th><th>Type</th><th>Availability</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr><td><?php echo e($row['title']); ?></td><td><?php echo e($row['listing_type']); ?></td><td><?php echo e($row['availability_status']); ?></td><td><?php echo (int) $row['is_active'] ? 'Active' : 'Inactive'; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="4" class="muted">No marketplace listings yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
