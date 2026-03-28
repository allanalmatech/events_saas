(function () {
    var body = document.body;
    var sidebarToggle = document.getElementById('sidebar-toggle');
    var sidebarOpen = document.getElementById('sidebar-open');

    function isMobile() {
        return window.matchMedia('(max-width: 920px)').matches;
    }

    function applySavedSidebarState() {
        var saved = localStorage.getItem('sidebarCollapsed') === '1';
        if (!isMobile() && saved) {
            body.classList.add('sidebar-collapsed');
        }
    }

    applySavedSidebarState();

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0');
            var icon = sidebarToggle.querySelector('i');
            if (icon) {
                icon.className = body.classList.contains('sidebar-collapsed') ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
            }
        });

        var initIcon = sidebarToggle.querySelector('i');
        if (initIcon) {
            initIcon.className = body.classList.contains('sidebar-collapsed') ? 'fa-solid fa-angles-right' : 'fa-solid fa-angles-left';
        }
    }

    if (sidebarOpen) {
        sidebarOpen.addEventListener('click', function () {
            body.classList.add('sidebar-mobile-open');
        });
    }

    document.addEventListener('click', function (event) {
        var confirmTarget = event.target.closest('[data-confirm]');
        if (confirmTarget) {
            var msg = confirmTarget.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                event.preventDefault();
                return;
            }
        }

        var closeTarget = event.target.closest('[data-sidebar-close]');
        if (closeTarget) {
            body.classList.remove('sidebar-mobile-open');
        }

        var navLink = event.target.closest('.sidebar a');
        if (navLink && isMobile()) {
            body.classList.remove('sidebar-mobile-open');
        }

        var submenuToggle = event.target.closest('[data-submenu-toggle]');
        if (submenuToggle) {
            if (!body.classList.contains('sidebar-collapsed') || isMobile()) {
                var group = submenuToggle.closest('.menu-group');
                if (group) {
                    group.classList.toggle('open');
                }
            }
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            body.classList.remove('sidebar-mobile-open');
            applySavedSidebarState();
        }
    });

    var clockEl = document.getElementById('live-clock');
    if (clockEl) {
        var renderClock = function () {
            var now = new Date();
            var formatted = now.toLocaleString(undefined, {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            clockEl.textContent = formatted;
        };
        renderClock();
        window.setInterval(renderClock, 1000);
    }
})();
