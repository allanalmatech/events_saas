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
    $favoriteRows = [];
    $imagesByListing = [];
    $profile = null;
    $csrf = csrf_token();

    ensure_marketplace_social_tables();

    if ($tenantId > 0 && ($mysqli = db_try())) {
        $p = $mysqli->prepare('SELECT public_name, about_text, contact_email, contact_phone, location_text, is_public FROM marketplace_profiles WHERE tenant_id = ? LIMIT 1');
        $p->bind_param('i', $tenantId);
        $p->execute();
        $profile = $p->get_result()->fetch_assoc();
        $p->close();

        $baseSelect = 'SELECT c.id, c.tenant_id, c.title, c.listing_type, c.description, c.availability_status, c.media_path, c.is_active, c.created_at,
            COALESCE(NULLIF(mp.public_name, ""), t.business_name) AS provider_name,
            COALESCE(mp.contact_phone, "") AS provider_phone,
            (SELECT COUNT(*) FROM marketplace_listing_views v WHERE v.listing_id = c.id) AS viewer_count,
            (SELECT COUNT(*) FROM marketplace_listing_likes l WHERE l.listing_id = c.id) AS like_count,
            (SELECT COUNT(*) FROM marketplace_listing_comments cm WHERE cm.listing_id = c.id) AS comment_count,
            EXISTS(SELECT 1 FROM marketplace_listing_likes ml WHERE ml.listing_id = c.id AND ml.user_id = ?) AS liked_by_me
            FROM marketplace_catalogue c
            INNER JOIN tenants t ON t.id = c.tenant_id
            LEFT JOIN marketplace_profiles mp ON mp.tenant_id = c.tenant_id';

        $mine = $mysqli->prepare($baseSelect . ' WHERE c.tenant_id = ? ORDER BY c.id DESC LIMIT 40');
        $mine->bind_param('ii', $userId, $tenantId);
        $mine->execute();
        $myRows = $mine->get_result()->fetch_all(MYSQLI_ASSOC);
        $mine->close();

        $feed = $mysqli->prepare($baseSelect . ' WHERE c.is_active = 1 AND (c.tenant_id = ? OR IFNULL(mp.is_public, 0) = 1) ORDER BY c.id DESC LIMIT 40');
        $feed->bind_param('ii', $userId, $tenantId);
        $feed->execute();
        $feedRows = $feed->get_result()->fetch_all(MYSQLI_ASSOC);
        $feed->close();

        foreach ($feedRows as $row) {
            if (!empty($row['liked_by_me'])) {
                $favoriteRows[] = $row;
            }
        }

        $allRows = array_merge($feedRows, $myRows);
        if ($allRows) {
            $listingIds = [];
            foreach ($allRows as $r) {
                $listingIds[] = (int) ($r['id'] ?? 0);
            }
            $listingIds = array_values(array_unique(array_filter($listingIds)));
            if ($listingIds) {
                $in = implode(',', array_map('intval', $listingIds));
                $imgRes = $mysqli->query('SELECT listing_id, image_path FROM marketplace_listing_images WHERE listing_id IN (' . $in . ') ORDER BY sort_order ASC, id ASC');
                while ($imgRes && ($img = $imgRes->fetch_assoc())) {
                    $lid = (int) ($img['listing_id'] ?? 0);
                    if ($lid <= 0) {
                        continue;
                    }
                    if (!isset($imagesByListing[$lid])) {
                        $imagesByListing[$lid] = [];
                    }
                    if (count($imagesByListing[$lid]) < 4) {
                        $imagesByListing[$lid][] = (string) ($img['image_path'] ?? '');
                    }
                }
            }

            foreach ($allRows as $r) {
                $lid = (int) ($r['id'] ?? 0);
                if ($lid <= 0 || isset($imagesByListing[$lid])) {
                    continue;
                }
                $fallback = trim((string) ($r['media_path'] ?? ''));
                if ($fallback !== '') {
                    $imagesByListing[$lid] = [$fallback];
                }
            }
        }
    }

    $renderCard = function (array $row, string $instanceKey) use ($tenantId, $imagesByListing): void {
        $listingId = (int) ($row['id'] ?? 0);
        $images = $imagesByListing[$listingId] ?? [];
        $coverImage = $images ? $images[0] : '';
        $isOwnListing = (int) ($row['tenant_id'] ?? 0) === $tenantId;
        $providerPhoneRaw = (string) ($row['provider_phone'] ?? '');
        $providerPhoneWa = marketplace_whatsapp_phone($providerPhoneRaw);
        $commentPanelId = 'comment-panel-' . $instanceKey;
        $commentListId = 'comment-list-' . $instanceKey;
        $commentTextareaId = 'comment-text-' . $instanceKey;
        $emojiPickerId = 'emoji-picker-' . $instanceKey;
        ?>
        <article class="feed-card" data-listing-id="<?php echo $listingId; ?>">
            <div class="post-head">
                <div>
                    <div class="post-provider"><?php echo e((string) ($row['provider_name'] ?? 'Provider')); ?></div>
                    <div class="post-time muted"><?php echo e((string) ($row['created_at'] ?? '')); ?></div>
                </div>
                <span class="chip"><?php echo e((string) ($row['listing_type'] ?? 'service')); ?></span>
            </div>

            <?php if ($coverImage !== ''): ?>
                <div class="post-image-wrap">
                    <img src="<?php echo e(app_url($coverImage)); ?>" alt="Ad image" class="post-image">
                    <?php if (count($images) > 1): ?><span class="post-image-count">+<?php echo count($images) - 1; ?></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <h4 class="post-title"><?php echo e((string) ($row['title'] ?? 'Untitled')); ?></h4>
            <div class="post-description"><?php echo nl2br(e((string) ($row['description'] ?? ''))); ?></div>
            <div class="post-sub muted">Availability: <?php echo e((string) ($row['availability_status'] ?? '')); ?><?php if ($isOwnListing): ?> · Your Ad<?php endif; ?></div>

            <div class="post-stats">
                <form method="post" action="<?php echo e(app_url('actions/toggle_marketplace_like.php')); ?>">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                    <button class="stat-btn" type="submit" title="Like"><i class="fa-regular fa-heart"></i> <span><?php echo (int) ($row['like_count'] ?? 0); ?></span></button>
                </form>
                <button class="stat-btn" type="button" title="Comments" data-comment-toggle="<?php echo e($commentPanelId); ?>" data-comment-list="<?php echo e($commentListId); ?>" data-listing-id="<?php echo $listingId; ?>"><i class="fa-regular fa-comment"></i> <span><?php echo (int) ($row['comment_count'] ?? 0); ?></span></button>
                <button class="stat-btn" type="button" title="View" data-modal-open="view-ad-<?php echo $listingId; ?>" data-track-view="<?php echo $listingId; ?>"><i class="fa-regular fa-eye"></i> <span><?php echo (int) ($row['viewer_count'] ?? 0); ?></span></button>
                <button class="stat-btn" type="button" title="Share" data-modal-open="share-ad-<?php echo $listingId; ?>"><i class="fa-solid fa-share-nodes"></i></button>
            </div>

            <div class="feed-comment-compose" id="<?php echo e($commentPanelId); ?>" hidden>
                <form method="post" action="<?php echo e(app_url('actions/save_marketplace_comment.php')); ?>" class="feed-comment-box">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                    <div class="field" style="margin:0 0 8px 0;"><textarea id="<?php echo e($commentTextareaId); ?>" name="comment_text" maxlength="1000" placeholder="Write a comment... Emojis are allowed 😊" required></textarea></div>
                    <div class="emoji-row">
                        <button class="btn btn-ghost" type="button" data-emoji-toggle="<?php echo e($emojiPickerId); ?>">Emoji</button>
                        <div class="emoji-picker" id="<?php echo e($emojiPickerId); ?>" hidden>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="😀">😀</button>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="😂">😂</button>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="😍">😍</button>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="🔥">🔥</button>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="🙌">🙌</button>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="👍">👍</button>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="❤️">❤️</button>
                            <button type="button" data-emoji-add="<?php echo e($commentTextareaId); ?>" data-emoji-value="🎉">🎉</button>
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">Post Comment</button>
                </form>
                <div id="<?php echo e($commentListId); ?>" class="feed-comments"><div class="muted">Click the comment icon to load comments.</div></div>
            </div>

            <?php if ($providerPhoneWa !== '' && !$isOwnListing): ?>
                <div class="post-footer muted"><i class="fa-brands fa-whatsapp"></i> DM available for direct order</div>
            <?php endif; ?>
        </article>
        <?php
    };

    $modalRows = [];
    foreach (array_merge($feedRows, $myRows) as $r) {
        $lid = (int) ($r['id'] ?? 0);
        if ($lid > 0 && !isset($modalRows[$lid])) {
            $modalRows[$lid] = $r;
        }
    }
    ?>
    <style>
        .market-toolbar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; align-items:center; justify-content:space-between; }
        .market-kpi { display:flex; flex-wrap:wrap; gap:8px; }
        .market-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
        .market-tab-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .market-tab-panel { display:none; }
        .market-tab-panel.active { display:block; }

        .feed-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; }
        .feed-card { border:1px solid var(--line); border-radius:14px; padding:10px; background:var(--card); }
        .post-head { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:8px; }
        .post-provider { font-weight:700; font-size:14px; }
        .post-time { font-size:12px; }
        .chip { display:inline-flex; padding:2px 8px; border-radius:999px; border:1px solid var(--line); font-size:12px; }
        .post-image-wrap { position:relative; margin-bottom:8px; }
        .post-image { display:block; width:100%; max-height:250px; object-fit:cover; border-radius:10px; border:1px solid var(--line); background:var(--soft); }
        .post-image-count { position:absolute; right:8px; bottom:8px; background:rgba(0,0,0,0.68); color:#fff; font-size:12px; border-radius:999px; padding:3px 8px; }
        .post-title { margin:0 0 6px 0; font-size:15px; }
        .post-description { margin-bottom:6px; color:var(--text); }
        .post-sub { margin-bottom:8px; font-size:12px; }

        .post-stats { display:flex; align-items:center; gap:6px; padding-top:8px; border-top:1px solid var(--line); }
        .post-stats form { margin:0; }
        .stat-btn { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--line); background:var(--soft); border-radius:999px; padding:6px 10px; cursor:pointer; color:inherit; }
        .stat-btn:hover { border-color:var(--primary); }

        .feed-comment-compose { margin:8px 0 0 0; padding:10px; border:1px solid var(--line); border-radius:10px; background:var(--soft); }
        .feed-comment-box textarea { min-height:58px; }
        .emoji-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .emoji-picker { display:flex; flex-wrap:wrap; gap:6px; }
        .emoji-picker button { border:1px solid var(--line); background:var(--card); border-radius:8px; padding:4px 6px; cursor:pointer; }

        .feed-comments { margin-top:10px; display:flex; flex-direction:column; gap:8px; }
        .feed-comment { border:1px solid var(--line); border-radius:10px; padding:8px; background:var(--card); }
        .feed-comment-head { display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:4px; }
        .feed-comment-tools { display:flex; gap:6px; }
        .feed-comment-edit { margin-top:6px; }
        .post-footer { margin-top:8px; font-size:12px; }

        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:640px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .share-options { display:flex; flex-wrap:wrap; gap:8px; }

        @media (max-width: 860px) {
            .market-toolbar { align-items:stretch; }
            .market-kpi .btn { width:100%; }
            .feed-grid { grid-template-columns:1fr; }
            .post-stats { flex-wrap:wrap; }
        }
    </style>

    <section class="card" style="margin-bottom:12px;">
        <div class="market-toolbar">
            <div>
                <h3 style="margin:0 0 4px 0;">Marketplace Feed</h3>
                <div class="muted">Clean social feed with quick reactions, lazy comments, and sharing.</div>
            </div>
            <div class="market-kpi">
                <button class="btn btn-ghost" type="button" data-modal-open="market-profile-modal">Marketplace Profile</button>
                <button class="btn btn-primary" type="button" data-modal-open="publish-ad-modal">+ Publish Ad</button>
            </div>
        </div>

        <div class="market-tabs">
            <button class="btn btn-ghost market-tab-btn active" type="button" data-tab-target="community">Community</button>
            <button class="btn btn-ghost market-tab-btn" type="button" data-tab-target="favorites">Favorites</button>
            <button class="btn btn-ghost market-tab-btn" type="button" data-tab-target="my-ads">My Ads</button>
        </div>

        <div class="market-tab-panel active" data-tab-panel="community">
            <div class="feed-grid">
                <?php foreach ($feedRows as $idx => $row): ?>
                    <?php $renderCard($row, 'community-' . (int) ($row['id'] ?? 0) . '-' . $idx); ?>
                <?php endforeach; ?>
                <?php if (!$feedRows): ?><div class="muted">No public ads in the feed yet.</div><?php endif; ?>
            </div>
        </div>

        <div class="market-tab-panel" data-tab-panel="favorites">
            <div class="feed-grid">
                <?php foreach ($favoriteRows as $idx => $row): ?>
                    <?php $renderCard($row, 'favorite-' . (int) ($row['id'] ?? 0) . '-' . $idx); ?>
                <?php endforeach; ?>
                <?php if (!$favoriteRows): ?><div class="muted">No favorites yet. Tap the heart on any ad.</div><?php endif; ?>
            </div>
        </div>

        <div class="market-tab-panel" data-tab-panel="my-ads">
            <div class="feed-grid">
                <?php foreach ($myRows as $idx => $row): ?>
                    <?php $renderCard($row, 'mine-' . (int) ($row['id'] ?? 0) . '-' . $idx); ?>
                <?php endforeach; ?>
                <?php if (!$myRows): ?><div class="muted">No ads yet. Publish your first ad.</div><?php endif; ?>
            </div>
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
            <form method="post" action="<?php echo e(app_url('actions/save_marketplace_listing.php')); ?>" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Title</label><input name="title" required></div>
                <div class="field"><label>Type</label><select name="listing_type"><option value="service">Service</option><option value="item">Item</option></select></div>
                <div class="field"><label>Description</label><textarea name="description"></textarea></div>
                <div class="field"><label>Ad Images (up to 4)</label><input type="file" name="listing_images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple></div>
                <div class="field"><label>Availability</label><input name="availability_status" value="Available"></div>
                <button class="btn btn-primary" type="submit">Publish</button>
            </form>
        </div>
    </div>

    <?php foreach ($modalRows as $listingId => $row): ?>
        <?php
        $providerPhoneWa = marketplace_whatsapp_phone((string) ($row['provider_phone'] ?? ''));
        $listingImages = $imagesByListing[(int) $listingId] ?? [];
        ?>
        <div class="modal-backdrop" id="view-ad-<?php echo (int) $listingId; ?>">
            <div class="card modal-card">
                <div class="modal-header"><h3 style="margin:0;"><?php echo e((string) ($row['title'] ?? '')); ?></h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                <div class="field"><label>Provider</label><div><?php echo e((string) ($row['provider_name'] ?? '')); ?></div></div>
                <div class="field"><label>Type</label><div><?php echo e((string) ($row['listing_type'] ?? '')); ?></div></div>
                <div class="field"><label>Availability</label><div><?php echo e((string) ($row['availability_status'] ?? '')); ?></div></div>
                <?php if ($listingImages): ?>
                    <div class="field"><label>Images</label>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;">
                            <?php foreach ($listingImages as $imgPath): ?>
                                <img src="<?php echo e(app_url($imgPath)); ?>" alt="Ad image" style="width:100%;height:110px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="field"><label>Description</label><div><?php echo nl2br(e((string) ($row['description'] ?? ''))); ?></div></div>
                <div class="muted">Viewers: <?php echo (int) ($row['viewer_count'] ?? 0); ?> | Likes: <?php echo (int) ($row['like_count'] ?? 0); ?> | Comments: <?php echo (int) ($row['comment_count'] ?? 0); ?></div>
            </div>
        </div>

        <div class="modal-backdrop" id="share-ad-<?php echo (int) $listingId; ?>">
            <div class="card modal-card">
                <div class="modal-header"><h3 style="margin:0;">Share This Ad</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                <div class="field"><label>Message Template</label><textarea id="share-template-<?php echo (int) $listingId; ?>">Hey! Did you know [company] offers [service or item] at [price]? Contact them today at [phone number].</textarea></div>
                <div class="field"><label>Company</label><input id="share-company-<?php echo (int) $listingId; ?>" value="<?php echo e((string) ($row['provider_name'] ?? '')); ?>"></div>
                <div class="field"><label>Service or Item</label><input id="share-service-<?php echo (int) $listingId; ?>" value="<?php echo e((string) ($row['title'] ?? '')); ?>"></div>
                <div class="field"><label>Price</label><input id="share-price-<?php echo (int) $listingId; ?>" placeholder="e.g. 50,000"></div>
                <div class="field"><label>Phone Number</label><input id="share-phone-<?php echo (int) $listingId; ?>" value="<?php echo e((string) ($row['provider_phone'] ?? '')); ?>"></div>
                <div class="share-options">
                    <button class="btn btn-ghost" type="button" data-share-action="whatsapp" data-share-id="<?php echo (int) $listingId; ?>">WhatsApp</button>
                    <button class="btn btn-ghost" type="button" data-share-action="facebook" data-share-id="<?php echo (int) $listingId; ?>">Facebook</button>
                    <button class="btn btn-ghost" type="button" data-share-action="email" data-share-id="<?php echo (int) $listingId; ?>">Email</button>
                    <button class="btn btn-ghost" type="button" data-share-action="copy" data-share-id="<?php echo (int) $listingId; ?>">Copy</button>
                </div>
            </div>
        </div>

        <?php if ($providerPhoneWa !== '' && (int) ($row['tenant_id'] ?? 0) !== $tenantId): ?>
            <div class="modal-backdrop" id="dm-ad-<?php echo (int) $listingId; ?>">
                <div class="card modal-card">
                    <div class="modal-header"><h3 style="margin:0;">WhatsApp DM for Order</h3><button class="btn btn-ghost" type="button" data-modal-close>Close</button></div>
                    <form class="wa-form" data-phone="<?php echo e($providerPhoneWa); ?>" data-provider="<?php echo e((string) ($row['provider_name'] ?? '')); ?>" data-title="<?php echo e((string) ($row['title'] ?? '')); ?>">
                        <div class="field"><label>Order Reference</label><input name="order_ref" required placeholder="e.g. ORD-1024"></div>
                        <div class="field"><label>Message</label><textarea name="dm_message" required placeholder="Hello, I want to order this listing."></textarea></div>
                        <button class="btn btn-primary" type="submit">Open WhatsApp</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script>
        (function () {
            var csrfToken = <?php echo json_encode($csrf); ?>;
            var commentsEndpoint = <?php echo json_encode(app_url('actions/get_marketplace_comments.php')); ?>;
            var trackedViews = {};
            var commentsCache = {};

            function esc(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function nl2br(str) {
                return esc(str).replace(/\n/g, '<br>');
            }

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

            function renderComments(listEl, listingId, comments) {
                if (!comments.length) {
                    listEl.innerHTML = '<div class="muted">No comments yet.</div>';
                    return;
                }

                var html = '';
                for (var i = 0; i < comments.length; i++) {
                    var c = comments[i];
                    html += '<div class="feed-comment">';
                    html += '<div class="feed-comment-head"><div><strong>' + esc(c.user_name) + '</strong> <span class="muted" style="font-size:12px;">' + esc(c.created_at) + '</span></div>';
                    if (c.can_edit || c.can_delete) {
                        html += '<div class="feed-comment-tools">';
                        if (c.can_edit) {
                            html += '<button class="btn btn-ghost" type="button" data-edit-toggle="edit-box-' + c.id + '">Edit</button>';
                        }
                        if (c.can_delete) {
                            html += '<form method="post" action="<?php echo e(app_url('actions/delete_marketplace_comment.php')); ?>">';
                            html += '<input type="hidden" name="_csrf" value="' + esc(csrfToken) + '">';
                            html += '<input type="hidden" name="comment_id" value="' + Number(c.id) + '">';
                            html += '<button class="btn btn-ghost" type="submit" data-confirm="Delete this comment?">Delete</button>';
                            html += '</form>';
                        }
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '<div>' + nl2br(c.comment_text) + '</div>';

                    if (c.can_edit) {
                        html += '<div class="feed-comment-edit" id="edit-box-' + c.id + '" hidden>';
                        html += '<form method="post" action="<?php echo e(app_url('actions/update_marketplace_comment.php')); ?>">';
                        html += '<input type="hidden" name="_csrf" value="' + esc(csrfToken) + '">';
                        html += '<input type="hidden" name="comment_id" value="' + Number(c.id) + '">';
                        html += '<div class="field" style="margin:6px 0;"><textarea name="comment_text" maxlength="1000" required>' + esc(c.comment_text) + '</textarea></div>';
                        html += '<button class="btn btn-primary" type="submit">Save</button>';
                        html += '</form>';
                        html += '</div>';
                    }

                    html += '</div>';
                }

                listEl.innerHTML = html;

                var editToggles = listEl.querySelectorAll('[data-edit-toggle]');
                for (var j = 0; j < editToggles.length; j++) {
                    editToggles[j].addEventListener('click', function () {
                        var targetId = this.getAttribute('data-edit-toggle');
                        var target = document.getElementById(targetId);
                        if (!target) {
                            return;
                        }
                        if (target.hasAttribute('hidden')) {
                            target.removeAttribute('hidden');
                        } else {
                            target.setAttribute('hidden', 'hidden');
                        }
                    });
                }
            }

            function loadComments(listingId, listEl) {
                if (!listingId || !listEl) {
                    return;
                }

                if (commentsCache[listingId]) {
                    renderComments(listEl, listingId, commentsCache[listingId]);
                    return;
                }

                listEl.innerHTML = '<div class="muted">Loading comments...</div>';
                fetch(commentsEndpoint + '?listing_id=' + encodeURIComponent(String(listingId)), {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        listEl.innerHTML = '<div class="muted">Could not load comments.</div>';
                        return;
                    }
                    commentsCache[listingId] = data.comments || [];
                    renderComments(listEl, listingId, commentsCache[listingId]);
                })
                .catch(function () {
                    listEl.innerHTML = '<div class="muted">Could not load comments.</div>';
                });
            }

            var tabButtons = document.querySelectorAll('.market-tab-btn');
            var tabPanels = document.querySelectorAll('.market-tab-panel');
            function activateTab(tabKey) {
                for (var i = 0; i < tabButtons.length; i++) {
                    tabButtons[i].classList.toggle('active', tabButtons[i].getAttribute('data-tab-target') === tabKey);
                }
                for (var j = 0; j < tabPanels.length; j++) {
                    tabPanels[j].classList.toggle('active', tabPanels[j].getAttribute('data-tab-panel') === tabKey);
                }
            }
            for (var t = 0; t < tabButtons.length; t++) {
                tabButtons[t].addEventListener('click', function () {
                    activateTab(this.getAttribute('data-tab-target'));
                });
            }

            var openButtons = document.querySelectorAll('[data-modal-open]');
            for (var a = 0; a < openButtons.length; a++) {
                openButtons[a].addEventListener('click', function () {
                    var modalId = this.getAttribute('data-modal-open');
                    openModal(modalId);
                    var listingId = this.getAttribute('data-track-view');
                    if (listingId) {
                        recordView(parseInt(listingId, 10));
                    }
                });
            }

            var closeButtons = document.querySelectorAll('[data-modal-close]');
            for (var b = 0; b < closeButtons.length; b++) {
                closeButtons[b].addEventListener('click', function () {
                    closeModal(this);
                });
            }

            var backdrops = document.querySelectorAll('.modal-backdrop');
            for (var c = 0; c < backdrops.length; c++) {
                backdrops[c].addEventListener('click', function (event) {
                    if (event.target === this) {
                        this.classList.remove('open');
                    }
                });
            }

            var commentToggles = document.querySelectorAll('[data-comment-toggle]');
            for (var d = 0; d < commentToggles.length; d++) {
                commentToggles[d].addEventListener('click', function () {
                    var panelId = this.getAttribute('data-comment-toggle');
                    var panel = document.getElementById(panelId);
                    var listId = this.getAttribute('data-comment-list');
                    var listEl = document.getElementById(listId);
                    var listingId = parseInt(this.getAttribute('data-listing-id') || '0', 10);
                    if (!panel) {
                        return;
                    }

                    var hidden = panel.hasAttribute('hidden');
                    if (hidden) {
                        panel.removeAttribute('hidden');
                        loadComments(listingId, listEl);
                    } else {
                        panel.setAttribute('hidden', 'hidden');
                    }
                });
            }

            var emojiToggles = document.querySelectorAll('[data-emoji-toggle]');
            for (var e = 0; e < emojiToggles.length; e++) {
                emojiToggles[e].addEventListener('click', function () {
                    var pickerId = this.getAttribute('data-emoji-toggle');
                    var picker = document.getElementById(pickerId);
                    if (!picker) {
                        return;
                    }
                    if (picker.hasAttribute('hidden')) {
                        picker.removeAttribute('hidden');
                    } else {
                        picker.setAttribute('hidden', 'hidden');
                    }
                });
            }

            var emojiAdders = document.querySelectorAll('[data-emoji-add]');
            for (var f = 0; f < emojiAdders.length; f++) {
                emojiAdders[f].addEventListener('click', function () {
                    var textareaId = this.getAttribute('data-emoji-add');
                    var emojiValue = this.getAttribute('data-emoji-value') || '';
                    var textarea = document.getElementById(textareaId);
                    if (!textarea || !emojiValue) {
                        return;
                    }
                    textarea.value = textarea.value + emojiValue;
                    textarea.focus();
                });
            }

            function buildShareText(id) {
                var template = document.getElementById('share-template-' + id);
                var company = document.getElementById('share-company-' + id);
                var service = document.getElementById('share-service-' + id);
                var price = document.getElementById('share-price-' + id);
                var phone = document.getElementById('share-phone-' + id);

                var text = (template ? template.value : '');
                text = text.replace(/\[company\]/gi, company ? company.value : '');
                text = text.replace(/\[service or item\]/gi, service ? service.value : '');
                text = text.replace(/\[price\]/gi, price ? price.value : '');
                text = text.replace(/\[phone number\]/gi, phone ? phone.value : '');
                return text;
            }

            var shareButtons = document.querySelectorAll('[data-share-action]');
            for (var s = 0; s < shareButtons.length; s++) {
                shareButtons[s].addEventListener('click', function () {
                    var action = this.getAttribute('data-share-action');
                    var id = this.getAttribute('data-share-id') || '';
                    var text = buildShareText(id);
                    var pageUrl = window.location.href.split('#')[0] + '#listing-' + id;

                    if (action === 'whatsapp') {
                        window.open('https://wa.me/?text=' + encodeURIComponent(text + ' ' + pageUrl), '_blank', 'noopener');
                        return;
                    }
                    if (action === 'facebook') {
                        window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(pageUrl) + '&quote=' + encodeURIComponent(text), '_blank', 'noopener');
                        return;
                    }
                    if (action === 'email') {
                        window.location.href = 'mailto:?subject=' + encodeURIComponent('Check this marketplace ad') + '&body=' + encodeURIComponent(text + '\n\n' + pageUrl);
                        return;
                    }
                    if (action === 'copy') {
                        var payload = text + '\n' + pageUrl;
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(payload);
                        }
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
                    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(text), '_blank', 'noopener');
                });
            }
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
