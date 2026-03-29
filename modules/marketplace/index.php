<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Marketplace';
$moduleKey = 'marketplace';
$modulePermission = 'marketplace.view';
$moduleDescription = 'Publish tenant services/catalogue and discover community collaboration opportunities.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $user = auth_user() ?: [];
    $userId = (int) ($user['id'] ?? 0);

    $feedRows = [];
    $myRows = [];
    $commentsByListing = [];
    $profile = null;
    $csrf = csrf_token();

    ensure_marketplace_social_tables();

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $p = $mysqli->prepare('SELECT public_name, about_text, contact_email, contact_phone, location_text, is_public FROM marketplace_profiles WHERE tenant_id = ? LIMIT 1');
        $p->bind_param('i', $tenantId);
        $p->execute();
        $profile = $p->get_result()->fetch_assoc();
        $p->close();

        $mine = $mysqli->prepare('SELECT c.id, c.title, c.listing_type, c.availability_status, c.is_active, c.created_at,
                (SELECT COUNT(*) FROM marketplace_listing_views v WHERE v.listing_id = c.id) AS viewer_count,
                (SELECT COUNT(*) FROM marketplace_listing_likes l WHERE l.listing_id = c.id) AS like_count,
                (SELECT COUNT(*) FROM marketplace_listing_comments cm WHERE cm.listing_id = c.id) AS comment_count
                FROM marketplace_catalogue c
                WHERE c.tenant_id = ?
                ORDER BY c.id DESC LIMIT 40');
        $mine->bind_param('i', $tenantId);
        $mine->execute();
        $myRows = $mine->get_result()->fetch_all(MYSQLI_ASSOC);
        $mine->close();

        $feed = $mysqli->prepare('SELECT c.id, c.tenant_id, c.title, c.listing_type, c.description, c.availability_status, c.created_at,
                COALESCE(NULLIF(mp.public_name, ""), t.business_name) AS provider_name,
                COALESCE(mp.contact_phone, "") AS provider_phone,
                (SELECT COUNT(*) FROM marketplace_listing_views v WHERE v.listing_id = c.id) AS viewer_count,
                (SELECT COUNT(*) FROM marketplace_listing_likes l WHERE l.listing_id = c.id) AS like_count,
                (SELECT COUNT(*) FROM marketplace_listing_comments cm WHERE cm.listing_id = c.id) AS comment_count,
                EXISTS(SELECT 1 FROM marketplace_listing_likes ml WHERE ml.listing_id = c.id AND ml.user_id = ?) AS liked_by_me
                FROM marketplace_catalogue c
                INNER JOIN tenants t ON t.id = c.tenant_id
                LEFT JOIN marketplace_profiles mp ON mp.tenant_id = c.tenant_id
                WHERE c.is_active = 1 AND (c.tenant_id = ? OR IFNULL(mp.is_public, 0) = 1)
                ORDER BY c.id DESC LIMIT 40');
        $feed->bind_param('ii', $userId, $tenantId);
        $feed->execute();
        $feedRows = $feed->get_result()->fetch_all(MYSQLI_ASSOC);
        $feed->close();

        if ($feedRows) {
            $listingIds = array_map(static fn(array $r): int => (int) $r['id'], $feedRows);
            $listingIds = array_values(array_filter($listingIds));
            if ($listingIds) {
                $in = implode(',', array_map('intval', $listingIds));
                $commentSql = 'SELECT mc.id, mc.listing_id, mc.user_id, mc.comment_text, mc.created_at, mc.updated_at,
                    TIMESTAMPDIFF(SECOND, mc.created_at, NOW()) AS age_seconds,
                    u.full_name
                    FROM marketplace_listing_comments mc
                    INNER JOIN tenant_users u ON u.id = mc.user_id
                    WHERE mc.listing_id IN (' . $in . ')
                    ORDER BY mc.created_at DESC
                    LIMIT 800';
                $res = $mysqli->query($commentSql);
                while ($res && ($comment = $res->fetch_assoc())) {
                    $lid = (int) $comment['listing_id'];
                    if (!isset($commentsByListing[$lid])) {
                        $commentsByListing[$lid] = [];
                    }
                    if (count($commentsByListing[$lid]) < 8) {
                        $commentsByListing[$lid][] = $comment;
                    }
                }
            }
        }
    }
    ?>
    <style>
        .market-toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; align-items:center; justify-content:space-between; }
        .market-kpi { display:flex; flex-wrap:wrap; gap:8px; }
        .feed-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:12px; }
        .feed-card { border:1px solid var(--line); border-radius:12px; padding:12px; background:var(--card); }
        .feed-head { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
        .feed-meta { display:flex; flex-wrap:wrap; gap:8px; margin:8px 0; color:var(--muted); font-size:12px; }
        .chip { display:inline-flex; padding:3px 8px; border-radius:999px; border:1px solid var(--line); font-size:12px; }
        .feed-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
        .feed-comment-box textarea { min-height:58px; }
        .feed-comments { margin-top:10px; display:flex; flex-direction:column; gap:8px; }
        .feed-comment { border:1px solid var(--line); border-radius:10px; padding:8px; background:var(--soft); }
        .feed-comment-head { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:4px; }
        .feed-comment-tools { display:flex; gap:6px; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:620px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    </style>

    <section class="card" style="margin-bottom:12px;">
        <div class="market-toolbar">
            <div>
                <h3 style="margin:0 0 4px 0;">Marketplace Feed</h3>
                <div class="muted">Discover active ads, react with likes, and discuss in comments.</div>
            </div>
            <div class="market-kpi">
                <button class="btn btn-ghost" type="button" data-modal-open="market-profile-modal">Marketplace Profile</button>
                <button class="btn btn-primary" type="button" data-modal-open="publish-ad-modal">+ Publish Ad</button>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:12px;">
        <h3 style="margin-top:0;">My Ads</h3>
        <table class="table">
            <thead><tr><th>Title</th><th>Type</th><th>Availability</th><th>Status</th><th>Viewers</th><th>Likes</th><th>Comments</th></tr></thead>
            <tbody>
            <?php foreach ($myRows as $row): ?>
                <tr>
                    <td><?php echo e($row['title']); ?></td>
                    <td><?php echo e($row['listing_type']); ?></td>
                    <td><?php echo e($row['availability_status']); ?></td>
                    <td><?php echo (int) $row['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td><?php echo (int) ($row['viewer_count'] ?? 0); ?></td>
                    <td><?php echo (int) ($row['like_count'] ?? 0); ?></td>
                    <td><?php echo (int) ($row['comment_count'] ?? 0); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$myRows): ?><tr><td colspan="7" class="muted">No ads yet. Publish your first ad.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h3 style="margin-top:0;">Community Feed</h3>
        <div class="feed-grid">
            <?php foreach ($feedRows as $row): ?>
                <?php
                $listingId = (int) $row['id'];
                $likedByMe = !empty($row['liked_by_me']);
                $providerPhoneRaw = (string) ($row['provider_phone'] ?? '');
                $providerPhoneWa = marketplace_whatsapp_phone($providerPhoneRaw);
                $comments = $commentsByListing[$listingId] ?? [];
                $isOwnListing = (int) $row['tenant_id'] === $tenantId;
                ?>
                <article class="feed-card">
                    <div class="feed-head">
                        <div>
                            <div style="font-weight:700;"><?php echo e($row['title']); ?></div>
                            <div class="muted" style="font-size:12px;">By <?php echo e($row['provider_name']); ?></div>
                        </div>
                        <span class="chip"><?php echo e($row['listing_type']); ?></span>
                    </div>

                    <div class="feed-meta">
                        <span>Availability: <?php echo e($row['availability_status']); ?></span>
                        <span>Posted: <?php echo e($row['created_at']); ?></span>
                        <?php if ($isOwnListing): ?><span class="chip">Your Ad</span><?php endif; ?>
                    </div>

                    <div style="margin:8px 0;"><?php echo nl2br(e((string) ($row['description'] ?? ''))); ?></div>

                    <div class="feed-meta">
                        <strong><?php echo (int) ($row['viewer_count'] ?? 0); ?></strong> viewers
                        <strong><?php echo (int) ($row['like_count'] ?? 0); ?></strong> likes
                        <strong><?php echo (int) ($row['comment_count'] ?? 0); ?></strong> comments
                    </div>

                    <div class="feed-actions">
                        <form method="post" action="<?php echo e(app_url('actions/toggle_marketplace_like.php')); ?>">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                            <button class="btn btn-ghost" type="submit"><?php echo $likedByMe ? 'Unlike' : 'Like'; ?></button>
                        </form>
                        <button class="btn btn-ghost" type="button" data-modal-open="view-ad-<?php echo $listingId; ?>" data-track-view="<?php echo $listingId; ?>">View Ad</button>
                        <?php if ($providerPhoneWa !== '' && !$isOwnListing): ?>
                            <button class="btn btn-ghost" type="button" data-modal-open="dm-ad-<?php echo $listingId; ?>">DM Order on WhatsApp</button>
                        <?php else: ?>
                            <button class="btn btn-ghost" type="button" disabled>No WhatsApp</button>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="<?php echo e(app_url('actions/save_marketplace_comment.php')); ?>" class="feed-comment-box" style="margin-top:10px;">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                        <div class="field" style="margin:0 0 8px 0;"><textarea name="comment_text" maxlength="1000" placeholder="Write a comment..." required></textarea></div>
                        <button class="btn btn-primary" type="submit">Comment</button>
                    </form>

                    <div class="feed-comments">
                        <?php foreach ($comments as $comment): ?>
                            <?php
                            $commentId = (int) $comment['id'];
                            $commentOwner = (int) $comment['user_id'] === $userId;
                            $canEditWindow = $commentOwner && ((int) ($comment['age_seconds'] ?? 999999) <= 120);
                            ?>
                            <div class="feed-comment">
                                <div class="feed-comment-head">
                                    <div>
                                        <strong><?php echo e($comment['full_name']); ?></strong>
                                        <span class="muted" style="font-size:12px;"><?php echo e($comment['created_at']); ?></span>
                                    </div>
                                    <?php if ($commentOwner): ?>
                                        <div class="feed-comment-tools">
                                            <?php if ($canEditWindow): ?>
                                                <button class="btn btn-ghost" type="button" data-modal-open="edit-comment-<?php echo $commentId; ?>">Edit</button>
                                            <?php else: ?>
                                                <span class="muted" style="font-size:12px;">Edit window closed</span>
                                            <?php endif; ?>
                                            <form method="post" action="<?php echo e(app_url('actions/delete_marketplace_comment.php')); ?>">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="comment_id" value="<?php echo $commentId; ?>">
                                                <button class="btn btn-ghost" type="submit" data-confirm="Delete this comment?">Delete</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div><?php echo nl2br(e((string) $comment['comment_text'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$comments): ?><div class="muted">No comments yet.</div><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$feedRows): ?><div class="muted">No public ads in the feed yet.</div><?php endif; ?>
        </div>
    </section>

    <div class="modal-backdrop" id="market-profile-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Marketplace Profile</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
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
        </div>
    </div>

    <div class="modal-backdrop" id="publish-ad-modal">
        <div class="card modal-card">
            <div class="modal-header"><h3 style="margin:0;">Publish Ad</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
            <form method="post" action="<?php echo e(app_url('actions/save_marketplace_listing.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Title</label><input name="title" required></div>
                <div class="field"><label>Type</label><select name="listing_type"><option value="service">Service</option><option value="item">Item</option></select></div>
                <div class="field"><label>Description</label><textarea name="description"></textarea></div>
                <div class="field"><label>Availability</label><input name="availability_status" value="Available"></div>
                <button class="btn btn-primary" type="submit">Publish</button>
            </form>
        </div>
    </div>

    <?php foreach ($feedRows as $row): ?>
        <?php $listingId = (int) $row['id']; ?>
        <?php $providerPhoneWa = marketplace_whatsapp_phone((string) ($row['provider_phone'] ?? '')); ?>
        <div class="modal-backdrop" id="view-ad-<?php echo $listingId; ?>">
            <div class="card modal-card">
                <div class="modal-header"><h3 style="margin:0;"><?php echo e($row['title']); ?></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                <div class="field"><label>Provider</label><div><?php echo e($row['provider_name']); ?></div></div>
                <div class="field"><label>Type</label><div><?php echo e($row['listing_type']); ?></div></div>
                <div class="field"><label>Availability</label><div><?php echo e($row['availability_status']); ?></div></div>
                <div class="field"><label>Description</label><div><?php echo nl2br(e((string) ($row['description'] ?? ''))); ?></div></div>
                <div class="muted">Viewers: <?php echo (int) ($row['viewer_count'] ?? 0); ?> | Likes: <?php echo (int) ($row['like_count'] ?? 0); ?> | Comments: <?php echo (int) ($row['comment_count'] ?? 0); ?></div>
            </div>
        </div>

        <?php if ($providerPhoneWa !== '' && (int) $row['tenant_id'] !== $tenantId): ?>
            <div class="modal-backdrop" id="dm-ad-<?php echo $listingId; ?>">
                <div class="card modal-card">
                    <div class="modal-header"><h3 style="margin:0;">WhatsApp DM for Order</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                    <form class="wa-form" data-phone="<?php echo e($providerPhoneWa); ?>" data-provider="<?php echo e($row['provider_name']); ?>" data-title="<?php echo e($row['title']); ?>">
                        <div class="field"><label>Order Reference</label><input name="order_ref" required placeholder="e.g. ORD-1024"></div>
                        <div class="field"><label>Message</label><textarea name="dm_message" required placeholder="Hello, I want to order this listing."></textarea></div>
                        <button class="btn btn-primary" type="submit">Open WhatsApp</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php foreach ($commentsByListing as $commentRows): ?>
        <?php foreach ($commentRows as $comment): ?>
            <?php
            $commentId = (int) $comment['id'];
            $commentOwner = (int) $comment['user_id'] === $userId;
            $canEditWindow = $commentOwner && ((int) ($comment['age_seconds'] ?? 999999) <= 120);
            ?>
            <?php if ($canEditWindow): ?>
                <div class="modal-backdrop" id="edit-comment-<?php echo $commentId; ?>">
                    <div class="card modal-card">
                        <div class="modal-header"><h3 style="margin:0;">Edit Comment</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                        <form method="post" action="<?php echo e(app_url('actions/update_marketplace_comment.php')); ?>">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="comment_id" value="<?php echo $commentId; ?>">
                            <div class="field"><label>Comment</label><textarea name="comment_text" maxlength="1000" required><?php echo e($comment['comment_text']); ?></textarea></div>
                            <button class="btn btn-primary" type="submit">Save Comment</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <script>
        (function () {
            var csrfToken = <?php echo json_encode($csrf); ?>;
            var trackedViews = {};

            function openModal(id) {
                var modal = document.getElementById(id);
                if (modal) {
                    modal.classList.add('open');
                }
            }

            function closeModal(el) {
                var modal = el.closest('.modal-backdrop');
                if (modal) {
                    modal.classList.remove('open');
                }
            }

            function recordView(listingId) {
                if (!listingId || trackedViews[listingId]) {
                    return;
                }
                trackedViews[listingId] = true;

                var formData = new FormData();
                formData.append('_csrf', csrfToken);
                formData.append('listing_id', String(listingId));

                fetch(<?php echo json_encode(app_url('actions/record_marketplace_view.php')); ?>, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                }).catch(function () {
                });
            }

            var openButtons = document.querySelectorAll('[data-modal-open]');
            for (var i = 0; i < openButtons.length; i++) {
                openButtons[i].addEventListener('click', function () {
                    var modalId = this.getAttribute('data-modal-open');
                    openModal(modalId);
                    var listingId = this.getAttribute('data-track-view');
                    if (listingId) {
                        recordView(parseInt(listingId, 10));
                    }
                });
            }

            var closeButtons = document.querySelectorAll('[data-modal-close]');
            for (var j = 0; j < closeButtons.length; j++) {
                closeButtons[j].addEventListener('click', function () {
                    closeModal(this);
                });
            }

            var backdrops = document.querySelectorAll('.modal-backdrop');
            for (var k = 0; k < backdrops.length; k++) {
                backdrops[k].addEventListener('click', function (event) {
                    if (event.target === this) {
                        this.classList.remove('open');
                    }
                });
            }

            var waForms = document.querySelectorAll('.wa-form');
            for (var w = 0; w < waForms.length; w++) {
                waForms[w].addEventListener('submit', function (event) {
                    event.preventDefault();
                    var phone = this.getAttribute('data-phone') || '';
                    if (!phone) {
                        return;
                    }

                    var provider = this.getAttribute('data-provider') || '';
                    var title = this.getAttribute('data-title') || '';
                    var orderRefInput = this.querySelector('input[name="order_ref"]');
                    var messageInput = this.querySelector('textarea[name="dm_message"]');
                    var orderRef = orderRefInput ? orderRefInput.value.trim() : '';
                    var userMessage = messageInput ? messageInput.value.trim() : '';

                    if (!orderRef || !userMessage) {
                        return;
                    }

                    var text = 'Hello ' + provider + ', I want to place an order for "' + title + '". Order Ref: ' + orderRef + '. ' + userMessage;
                    var url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(text);
                    window.open(url, '_blank', 'noopener');
                });
            }
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
