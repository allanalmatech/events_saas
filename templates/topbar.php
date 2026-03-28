<?php
$user = auth_user();
$roleType = auth_role();
$themeSettings = current_user_theme_settings();
$currentThemeKey = (string) ($themeSettings['theme_key'] ?? 'brown_default');
$darkModeEnabled = (int) ($themeSettings['dark_mode_enabled'] ?? 0) === 1;
$ticketUnreadCount = 0;
$ticketUnreadRows = [];

if ($db = db_try()) {
    if ($roleType === 'director') {
        $countQ = $db->query('SELECT COUNT(*) c FROM support_tickets WHERE ticket_status IN ("open", "in_progress")');
        $ticketUnreadCount = (int) (($countQ ? $countQ->fetch_assoc()['c'] : 0) ?? 0);
        $listQ = $db->query('SELECT st.id, st.subject, st.updated_at, t.business_name FROM support_tickets st INNER JOIN tenants t ON t.id = st.tenant_id WHERE st.ticket_status IN ("open", "in_progress") ORDER BY st.updated_at DESC LIMIT 6');
        $ticketUnreadRows = $listQ ? $listQ->fetch_all(MYSQLI_ASSOC) : [];
    } elseif ($roleType === 'tenant_user' && !empty($user['tenant_id'])) {
        $tid = (int) $user['tenant_id'];
        $countStmt = $db->prepare('SELECT COUNT(*) c FROM support_tickets WHERE tenant_id = ? AND ticket_status IN ("open", "in_progress")');
        if ($countStmt) {
            $countStmt->bind_param('i', $tid);
            $countStmt->execute();
            $ticketUnreadCount = (int) (($countStmt->get_result()->fetch_assoc()['c']) ?? 0);
            $countStmt->close();
        }
        $listStmt = $db->prepare('SELECT id, subject, updated_at FROM support_tickets WHERE tenant_id = ? AND ticket_status IN ("open", "in_progress") ORDER BY updated_at DESC LIMIT 6');
        if ($listStmt) {
            $listStmt->bind_param('i', $tid);
            $listStmt->execute();
            $ticketUnreadRows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $listStmt->close();
        }
    }
}
$roleLabel = 'Guest';
if ($roleType === 'director') {
    $roleLabel = 'Director';
} elseif ($roleType === 'tenant_user') {
    if (!empty($user['is_super_admin'])) {
        $roleLabel = 'Super User';
    } else {
        $roleLabel = trim((string) ($user['role_name'] ?? ''));
        if ($roleLabel === '' && !empty($user['role_id']) && !empty($user['tenant_id'])) {
            $stmt = db()->prepare('SELECT role_name FROM roles WHERE id = ? AND tenant_id = ? LIMIT 1');
            if ($stmt) {
                $roleId = (int) $user['role_id'];
                $tenantId = (int) $user['tenant_id'];
                $stmt->bind_param('ii', $roleId, $tenantId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $roleLabel = trim((string) ($row['role_name'] ?? ''));
            }
        }
        if ($roleLabel === '') {
            $roleLabel = 'Staff';
        }
    }
}
$profileImagePath = trim((string) ($user['profile_image_path'] ?? ''));
$profileImageUrl = '';
if ($profileImagePath !== '') {
    if (preg_match('/^https?:\/\//i', $profileImagePath)) {
        $profileImageUrl = $profileImagePath;
    } else {
        $profileImageUrl = app_url(ltrim($profileImagePath, '/'));
    }
}
?>
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-controls">
            <button class="btn btn-ghost icon-btn" id="sidebar-open" type="button" title="Open Menu"><i class="fa-solid fa-bars"></i></button>
            <button class="btn btn-ghost icon-btn" id="sidebar-toggle" type="button" title="Collapse Sidebar"><i class="fa-solid fa-angles-left"></i></button>
        </div>
        <div>
            <strong><?php echo e($pageTitle ?? 'Dashboard'); ?></strong>
            <?php if (($moduleKey ?? '') === 'dashboard'): ?>
                <div class="muted" id="live-clock"></div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex; align-items:center; gap:10px;">
        <?php if ($roleType === 'tenant_user' || $roleType === 'director'): ?>
            <form method="post" action="<?php echo e(app_url('actions/change_theme.php')); ?>" id="account-theme-toggle-form">
                <?php if ($roleType === 'tenant_user'): ?>
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="theme_key" value="<?php echo e($currentThemeKey); ?>">
                    <input type="hidden" name="dark_mode_enabled" id="account-dark-mode-input" value="<?php echo $darkModeEnabled ? '1' : '0'; ?>">
                <?php endif; ?>
                <label class="theme-switch" for="account-dark-mode-toggle">
                    <span class="theme-switch-label" title="Light mode"><i class="fa-solid fa-sun"></i></span>
                    <input type="checkbox" class="theme-switch-input" id="account-dark-mode-toggle" aria-label="Toggle dark mode" <?php echo ($roleType === 'tenant_user' ? $darkModeEnabled : false) ? 'checked' : ''; ?>>
                    <span class="theme-switch-track" aria-hidden="true"><span class="theme-switch-knob"></span></span>
                    <span class="theme-switch-label" title="Dark mode"><i class="fa-solid fa-moon"></i></span>
                </label>
            </form>
        <?php endif; ?>

        <details class="account-menu">
            <summary class="account-trigger" title="Ticket notifications">
                <span class="account-icon notification-icon-wrap">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($ticketUnreadCount > 0): ?><span class="notif-badge"><?php echo (int) $ticketUnreadCount; ?></span><?php endif; ?>
                </span>
            </summary>
            <div class="account-panel card" style="min-width:300px;">
                <div><strong>Ticket Notifications</strong></div>
                <?php if ($ticketUnreadRows): ?>
                    <div style="margin-top:8px; display:grid; gap:6px;">
                        <?php foreach ($ticketUnreadRows as $ticket): ?>
                            <div class="muted" style="border:1px solid var(--outline); border-radius:8px; padding:6px 8px;">
                                <?php if (!empty($ticket['business_name'])): ?><div><strong><?php echo e($ticket['business_name']); ?></strong></div><?php endif; ?>
                                <div>#<?php echo (int) $ticket['id']; ?> - <?php echo e($ticket['subject']); ?></div>
                                <div style="font-size:11px;"><?php echo e($ticket['updated_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="muted" style="margin-top:8px;">No unread ticket alerts.</div>
                <?php endif; ?>
                <div style="margin-top:10px;"><a class="btn btn-ghost" href="<?php echo e(app_url('modules/tickets/index.php')); ?>">Open Tickets</a></div>
            </div>
        </details>

        <details class="account-menu">
            <summary class="account-trigger" title="Account menu">
                <span class="account-icon">
                    <?php if ($profileImageUrl !== ''): ?>
                        <img src="<?php echo e($profileImageUrl); ?>" alt="Profile picture" class="account-avatar">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                    <?php if ($ticketUnreadCount > 0): ?><span class="notif-badge"><?php echo (int) $ticketUnreadCount; ?></span><?php endif; ?>
                </span>
            </summary>
            <div class="account-panel card">
                <?php if ($profileImageUrl !== ''): ?>
                    <div style="margin-bottom:8px;"><img src="<?php echo e($profileImageUrl); ?>" alt="Profile picture" class="account-avatar account-avatar-large"></div>
                <?php endif; ?>
                <div><strong><?php echo e($user['name'] ?? 'Guest'); ?></strong></div>
                <div class="muted">Role: <?php echo e($roleLabel); ?></div>
                <div class="muted"><?php echo e($user['email'] ?? '-'); ?></div>
                <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn btn-ghost" href="<?php echo e(app_url('modules/profile/index.php')); ?>">Profile</a>
                    <a class="btn btn-ghost" href="<?php echo e(app_url('actions/logout.php')); ?>">Logout</a>
                </div>
            </div>
        </details>
    </div>

    <?php if ($roleType === 'tenant_user' || $roleType === 'director'): ?>
        <script>
            (function () {
                var toggle = document.getElementById('account-dark-mode-toggle');
                var input = document.getElementById('account-dark-mode-input');
                var form = document.getElementById('account-theme-toggle-form');
                if (!toggle || !input || !form) {
                    if (!toggle) {
                        return;
                    }
                }

                var isDirector = <?php echo $roleType === 'director' ? 'true' : 'false'; ?>;
                if (isDirector) {
                    var mode = localStorage.getItem('director_dark_mode') || '0';
                    toggle.checked = mode === '1';
                    document.body.classList.toggle('director-dark', toggle.checked);
                    toggle.addEventListener('change', function () {
                        var on = toggle.checked;
                        localStorage.setItem('director_dark_mode', on ? '1' : '0');
                        document.body.classList.toggle('director-dark', on);
                    });
                    return;
                }

                toggle.addEventListener('change', function () {
                    input.value = toggle.checked ? '1' : '0';
                    form.submit();
                });
            })();
        </script>
    <?php endif; ?>

    <script>
        (function () {
            var menus = document.querySelectorAll('.account-menu');
            for (var i = 0; i < menus.length; i++) {
                (function (menu) {
                    var closeTimer = null;

                    function openMenu() {
                        if (closeTimer) {
                            window.clearTimeout(closeTimer);
                            closeTimer = null;
                        }
                        menu.open = true;
                    }

                    function closeMenuDelayed() {
                        if (closeTimer) {
                            window.clearTimeout(closeTimer);
                        }
                        closeTimer = window.setTimeout(function () {
                            menu.open = false;
                        }, 180);
                    }

                    menu.addEventListener('mouseenter', function () {
                        openMenu();
                    });
                    menu.addEventListener('mouseleave', function () {
                        closeMenuDelayed();
                    });
                    menu.addEventListener('focusin', function () {
                        openMenu();
                    });
                    menu.addEventListener('focusout', function (event) {
                        if (!menu.contains(event.relatedTarget)) {
                            closeMenuDelayed();
                        }
                    });
                })(menus[i]);
            }
        })();
    </script>
</div>
