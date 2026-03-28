<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'My Profile';
$moduleKey = 'profile';
$modulePermission = 'dashboard.view';
$moduleDescription = 'Manage your personal account details.';

$contentRenderer = function (): void {
    $actor = auth_user() ?: [];
    $role = auth_role();
    $id = (int) ($actor['id'] ?? 0);
    $tenantId = (int) ($actor['tenant_id'] ?? 0);
    $row = null;

    if ($id > 0 && ($mysqli = db_try())) {
        if ($role === 'director') {
            $stmt = $mysqli->prepare('SELECT full_name, email FROM director_users WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        } else {
            $stmt = $mysqli->prepare('SELECT u.full_name, u.email, u.phone, u.profile_image_path, u.is_super_admin, r.role_name FROM tenant_users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.tenant_id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ii', $id, $tenantId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
        }
    }

    $profileImagePath = trim((string) ($row['profile_image_path'] ?? ($actor['profile_image_path'] ?? '')));
    $profileImageUrl = '';
    if ($profileImagePath !== '') {
        if (preg_match('/^https?:\/\//i', $profileImagePath)) {
            $profileImageUrl = $profileImagePath;
        } else {
            $profileImageUrl = app_url(ltrim($profileImagePath, '/'));
        }
    }

    $initials = strtoupper(substr((string) ($row['full_name'] ?? ($actor['name'] ?? 'U')), 0, 1));
    $roleLabel = 'Staff';
    $categoryLabel = 'Staff User';
    if ($role === 'director') {
        $roleLabel = 'Director';
        $categoryLabel = 'Director';
    } elseif (!empty($row['is_super_admin']) || !empty($actor['is_super_admin'])) {
        $roleLabel = 'Super User';
        $categoryLabel = 'Staff User';
    } else {
        $roleName = trim((string) ($row['role_name'] ?? ($actor['role_name'] ?? '')));
        if ($roleName !== '') {
            $roleLabel = $roleName;
        }
    }
    ?>
    <style>
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:9999; padding:16px; }
        .modal-backdrop.open { display:flex; }
        .modal-card { width:100%; max-width:680px; max-height:90vh; overflow:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .profile-grid { display:grid; grid-template-columns: 280px 1fr; gap:14px; align-items:start; }
        .profile-photo-wrap { display:flex; flex-direction:column; align-items:center; gap:10px; }
        .profile-photo {
            position:relative;
            width:170px;
            height:170px;
            border-radius:999px;
            overflow:hidden;
            border:1px solid var(--outline);
            background:var(--surface-soft);
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .profile-photo img { width:100%; height:100%; object-fit:cover; display:block; }
        .profile-fallback { font-size:52px; font-weight:700; color:var(--muted); }
        .photo-edit-btn {
            position:absolute;
            right:8px;
            bottom:8px;
            width:34px;
            height:34px;
            border-radius:999px;
            border:1px solid var(--outline);
            background:var(--surface);
            color:var(--text);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
        }
        .photo-edit-btn:hover { filter:brightness(1.06); }
        .crop-canvas-wrap { display:flex; justify-content:center; margin:10px 0; }
        .crop-canvas { width:280px; height:280px; border-radius:12px; border:1px solid var(--outline); background:#111; touch-action:none; cursor:grab; }
        .crop-canvas.dragging { cursor:grabbing; }
        .crop-controls { display:grid; gap:8px; }
        @media (max-width: 920px) {
            .profile-grid { grid-template-columns: 1fr; }
            .profile-photo { width:140px; height:140px; }
        }
    </style>

    <section class="profile-grid">
        <article class="card">
            <div class="profile-photo-wrap">
                <div class="profile-photo" id="profile-photo-frame">
                    <?php if ($profileImageUrl !== ''): ?>
                        <img src="<?php echo e($profileImageUrl); ?>" alt="Profile picture" id="profile-photo-preview">
                    <?php else: ?>
                        <span class="profile-fallback" id="profile-photo-fallback"><?php echo e($initials); ?></span>
                        <img src="" alt="Profile picture" id="profile-photo-preview" style="display:none;">
                    <?php endif; ?>
                    <button class="photo-edit-btn" id="open-photo-crop" type="button" title="Edit profile image"><i class="fa-solid fa-pencil"></i></button>
                </div>
                <div class="muted" style="text-align:center;">Your personal profile image</div>
                <div class="muted" style="text-align:center;"><strong>Role:</strong> <?php echo e($roleLabel); ?></div>
                <div class="muted" style="text-align:center;"><strong>Category:</strong> <?php echo e($categoryLabel); ?></div>
            </div>
        </article>

        <article class="card">
            <form method="post" action="<?php echo e(app_url('actions/update_profile.php')); ?>" id="profile-form">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="cropped_profile_image" id="cropped-profile-image" value="">

                <div class="field"><label>Full Name</label><input name="full_name" value="<?php echo e($row['full_name'] ?? ($actor['name'] ?? '')); ?>" required></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="<?php echo e($row['email'] ?? ($actor['email'] ?? '')); ?>" required></div>
                <div class="field"><label>Role</label><input value="<?php echo e($roleLabel); ?>" disabled></div>
                <div class="field"><label>Category</label><input value="<?php echo e($categoryLabel); ?>" disabled></div>
                <?php if ($role !== 'director'): ?>
                    <div class="field"><label>Phone</label><input name="phone" value="<?php echo e($row['phone'] ?? ''); ?>"></div>
                <?php endif; ?>
                <div class="field"><label>New Password (optional)</label><input type="password" name="new_password" minlength="8" placeholder="Leave blank to keep current password"></div>
                <button class="btn btn-primary" type="submit">Save Profile</button>
            </form>
        </article>
    </section>

    <div class="modal-backdrop" id="crop-photo-modal">
        <div class="card modal-card" style="max-width:520px;">
            <div class="modal-header">
                <h3 style="margin:0;">Edit Profile Image</h3>
                <button class="btn btn-ghost" type="button" data-modal-close>Close</button>
            </div>

            <input type="file" id="profile-file-input" accept="image/*" style="display:none;">
            <button class="btn btn-ghost" type="button" id="pick-photo-btn">Choose Image</button>

            <div class="crop-canvas-wrap">
                <canvas id="crop-canvas" class="crop-canvas" width="560" height="560"></canvas>
            </div>

            <div class="crop-controls">
                <label>Zoom</label>
                <input type="range" id="crop-zoom" min="1" max="3" step="0.01" value="1">
            </div>

            <div style="display:flex; gap:8px; margin-top:12px;">
                <button class="btn btn-primary" type="button" id="apply-crop-btn">Apply</button>
                <button class="btn btn-ghost" type="button" data-modal-close>Cancel</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var openBtn = document.getElementById('open-photo-crop');
            var modal = document.getElementById('crop-photo-modal');
            var closeBtns = modal ? modal.querySelectorAll('[data-modal-close]') : [];
            var pickBtn = document.getElementById('pick-photo-btn');
            var fileInput = document.getElementById('profile-file-input');
            var canvas = document.getElementById('crop-canvas');
            var zoomInput = document.getElementById('crop-zoom');
            var applyBtn = document.getElementById('apply-crop-btn');
            var hiddenInput = document.getElementById('cropped-profile-image');
            var preview = document.getElementById('profile-photo-preview');
            var fallback = document.getElementById('profile-photo-fallback');

            if (!openBtn || !modal || !canvas || !zoomInput || !applyBtn || !hiddenInput || !preview) {
                return;
            }

            var ctx = canvas.getContext('2d');
            var sourceImage = null;
            var zoom = 1;
            var offsetX = 0;
            var offsetY = 0;
            var dragging = false;
            var dragStartX = 0;
            var dragStartY = 0;

            function openModal() {
                modal.classList.add('open');
            }

            function closeModal() {
                modal.classList.remove('open');
            }

            function fitImage() {
                if (!sourceImage) {
                    return;
                }
                var target = canvas.width;
                var baseScale = Math.max(target / sourceImage.width, target / sourceImage.height);
                zoomInput.min = String(baseScale);
                if (parseFloat(zoomInput.value) < baseScale) {
                    zoomInput.value = String(baseScale);
                }
                zoom = parseFloat(zoomInput.value);
                offsetX = (target - sourceImage.width * zoom) / 2;
                offsetY = (target - sourceImage.height * zoom) / 2;
                draw();
            }

            function draw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#111';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                if (!sourceImage) {
                    ctx.fillStyle = '#777';
                    ctx.font = '22px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText('Choose an image', canvas.width / 2, canvas.height / 2);
                    return;
                }

                var drawW = sourceImage.width * zoom;
                var drawH = sourceImage.height * zoom;

                var minX = canvas.width - drawW;
                var minY = canvas.height - drawH;
                if (offsetX > 0) {
                    offsetX = 0;
                }
                if (offsetY > 0) {
                    offsetY = 0;
                }
                if (offsetX < minX) {
                    offsetX = minX;
                }
                if (offsetY < minY) {
                    offsetY = minY;
                }

                ctx.drawImage(sourceImage, offsetX, offsetY, drawW, drawH);
            }

            function pickFile() {
                fileInput.click();
            }

            function readFile(file) {
                if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (event) {
                    var img = new Image();
                    img.onload = function () {
                        sourceImage = img;
                        fitImage();
                    };
                    img.src = String(event.target.result || '');
                };
                reader.readAsDataURL(file);
            }

            function applyCrop() {
                if (!sourceImage) {
                    return;
                }
                var out = document.createElement('canvas');
                out.width = 512;
                out.height = 512;
                var outCtx = out.getContext('2d');
                outCtx.fillStyle = '#fff';
                outCtx.fillRect(0, 0, out.width, out.height);
                outCtx.drawImage(canvas, 0, 0, canvas.width, canvas.height, 0, 0, out.width, out.height);
                var dataUrl = out.toDataURL('image/jpeg', 0.82);
                hiddenInput.value = dataUrl;
                preview.src = dataUrl;
                preview.style.display = 'block';
                if (fallback) {
                    fallback.style.display = 'none';
                }
                closeModal();
            }

            openBtn.addEventListener('click', function () {
                openModal();
                draw();
            });

            for (var i = 0; i < closeBtns.length; i++) {
                closeBtns[i].addEventListener('click', closeModal);
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            pickBtn.addEventListener('click', pickFile);

            fileInput.addEventListener('change', function () {
                var file = this.files && this.files.length ? this.files[0] : null;
                readFile(file);
            });

            zoomInput.addEventListener('input', function () {
                zoom = parseFloat(this.value || '1');
                draw();
            });

            canvas.addEventListener('mousedown', function (event) {
                dragging = true;
                dragStartX = event.clientX;
                dragStartY = event.clientY;
                canvas.classList.add('dragging');
            });

            canvas.addEventListener('touchstart', function (event) {
                if (!event.touches || !event.touches.length) {
                    return;
                }
                dragging = true;
                dragStartX = event.touches[0].clientX;
                dragStartY = event.touches[0].clientY;
                canvas.classList.add('dragging');
            }, { passive: true });

            window.addEventListener('mouseup', function () {
                dragging = false;
                canvas.classList.remove('dragging');
            });

            window.addEventListener('touchend', function () {
                dragging = false;
                canvas.classList.remove('dragging');
            }, { passive: true });

            window.addEventListener('mousemove', function (event) {
                if (!dragging || !sourceImage) {
                    return;
                }
                var dx = event.clientX - dragStartX;
                var dy = event.clientY - dragStartY;
                dragStartX = event.clientX;
                dragStartY = event.clientY;
                offsetX += dx;
                offsetY += dy;
                draw();
            });

            window.addEventListener('touchmove', function (event) {
                if (!dragging || !sourceImage || !event.touches || !event.touches.length) {
                    return;
                }
                var dx = event.touches[0].clientX - dragStartX;
                var dy = event.touches[0].clientY - dragStartY;
                dragStartX = event.touches[0].clientX;
                dragStartY = event.touches[0].clientY;
                offsetX += dx;
                offsetY += dy;
                draw();
            }, { passive: true });

            applyBtn.addEventListener('click', applyCrop);
            draw();
        })();
    </script>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
