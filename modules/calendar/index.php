<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Calendar';
$moduleKey = 'calendar';
$modulePermission = 'calendar.view';
$moduleDescription = 'Monthly overview for events, return deadlines, and payment reminders.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $events = [];
    $isDirector = auth_role() === 'director';

    if ($mysqli = db_try()) {
        if ($isDirector) {
            $q = $mysqli->query('SELECT b.id, b.booking_ref, b.event_date, b.status, b.event_location, b.event_type, c.full_name AS customer_name, t.business_name FROM bookings b INNER JOIN customers c ON c.id = b.customer_id INNER JOIN tenants t ON t.id = b.tenant_id ORDER BY b.event_date ASC LIMIT 3000');
            $events = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];
        } elseif ($tenantId > 0) {
            $stmt = $mysqli->prepare('SELECT b.id, b.booking_ref, b.event_date, b.status, b.event_location, b.event_type, c.full_name AS customer_name FROM bookings b INNER JOIN customers c ON c.id = b.customer_id WHERE b.tenant_id = ? ORDER BY b.event_date ASC LIMIT 3000');
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }

    $eventsJson = json_encode($events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <section class="card">
        <style>
            .calendar-toolbar { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:12px; }
            .calendar-nav { display:flex; gap:8px; align-items:center; }
            .calendar-mode { display:flex; gap:8px; }
            .calendar-mode .btn.active { border-color:var(--primary); background:color-mix(in srgb, var(--primary) 18%, transparent); }
            .calendar-title { font-weight:700; min-width:190px; text-align:center; }
            .calendar-weekdays { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:8px; margin-bottom:8px; }
            .calendar-weekday { font-size:12px; color:var(--muted); text-align:center; }
            .calendar-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:8px; }
            .calendar-day { border:1px solid var(--outline); border-radius:10px; min-height:92px; padding:8px; background:var(--surface-soft); position:relative; cursor:default; }
            .calendar-day.outside { opacity:0.45; }
            .calendar-day.has-events { cursor:pointer; border-color: color-mix(in srgb, var(--primary) 45%, var(--outline)); }
            .calendar-day.selected { outline:2px solid color-mix(in srgb, var(--primary) 55%, transparent); }
            .day-num { font-weight:700; font-size:13px; }
            .event-dot { position:absolute; bottom:7px; right:8px; width:18px; height:18px; border-radius:999px; background:var(--primary); color:var(--on-primary); font-size:11px; display:inline-flex; align-items:center; justify-content:center; }
            .calendar-tooltip { position:fixed; background:var(--surface); border:1px solid var(--outline); border-radius:10px; padding:8px 10px; max-width:280px; z-index:10020; box-shadow:0 10px 24px rgba(0,0,0,0.28); pointer-events:none; font-size:12px; display:none; }
            .calendar-tooltip .muted { font-size:11px; }
            .week-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:8px; }
            .week-day { border:1px solid var(--outline); border-radius:10px; min-height:120px; padding:8px; background:var(--surface-soft); }
            .year-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
            .year-month { border:1px solid var(--outline); border-radius:10px; background:var(--surface-soft); padding:8px; }
            .year-month-title { font-size:13px; font-weight:700; margin-bottom:6px; }
            .year-days { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:4px; }
            .year-day { min-height:20px; border-radius:6px; font-size:11px; text-align:center; padding-top:2px; border:1px solid transparent; }
            .year-day.has-events { background:color-mix(in srgb, var(--primary) 28%, transparent); border-color:color-mix(in srgb, var(--primary) 55%, transparent); cursor:pointer; }
            .events-table-wrap { margin-top:14px; }
            @media (max-width:980px) { .year-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
            @media (max-width:760px) {
                .calendar-grid, .calendar-weekdays, .week-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
                .calendar-day, .week-day { min-height:86px; }
                .year-grid { grid-template-columns:1fr; }
            }
        </style>

        <div class="calendar-toolbar">
            <div class="calendar-nav">
                <button class="btn btn-ghost" type="button" id="cal-prev"><i class="fa-solid fa-chevron-left"></i></button>
                <div class="calendar-title" id="cal-title">Calendar</div>
                <button class="btn btn-ghost" type="button" id="cal-next"><i class="fa-solid fa-chevron-right"></i></button>
            </div>
            <div class="calendar-mode">
                <button class="btn btn-ghost active" type="button" data-cal-mode="month">Month</button>
                <button class="btn btn-ghost" type="button" data-cal-mode="week">Week</button>
                <button class="btn btn-ghost" type="button" data-cal-mode="year">Year</button>
            </div>
        </div>

        <div id="calendar-weekdays" class="calendar-weekdays"></div>
        <div id="calendar-view"></div>
        <div id="calendar-tooltip" class="calendar-tooltip"></div>

        <div class="events-table-wrap">
            <h3 style="margin-top:0;" id="day-events-title">Events</h3>
            <table class="table">
                <thead>
                    <tr>
                        <?php if ($isDirector): ?><th>Tenant</th><?php endif; ?>
                        <th>Booking Ref</th><th>Customer</th><th>Type</th><th>Location</th><th>Status</th>
                    </tr>
                </thead>
                <tbody id="day-events-body">
                    <tr><td colspan="<?php echo $isDirector ? '6' : '5'; ?>" class="muted">Select a day with events.</td></tr>
                </tbody>
            </table>
        </div>

        <script>
            (function () {
                var events = <?php echo $eventsJson ?: '[]'; ?> || [];
                var isDirector = <?php echo $isDirector ? 'true' : 'false'; ?>;
                var state = {
                    mode: 'month',
                    cursor: new Date(),
                    selectedDate: ''
                };

                var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

                var weekdaysEl = document.getElementById('calendar-weekdays');
                var viewEl = document.getElementById('calendar-view');
                var titleEl = document.getElementById('cal-title');
                var tooltipEl = document.getElementById('calendar-tooltip');
                var prevBtn = document.getElementById('cal-prev');
                var nextBtn = document.getElementById('cal-next');
                var modeBtns = document.querySelectorAll('[data-cal-mode]');
                var dayEventsTitle = document.getElementById('day-events-title');
                var dayEventsBody = document.getElementById('day-events-body');

                function pad(n) {
                    return String(n).padStart(2, '0');
                }

                function toDateKey(date) {
                    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
                }

                function fromDateKey(key) {
                    var p = String(key || '').split('-');
                    if (p.length !== 3) {
                        return null;
                    }
                    return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
                }

                function formatDateHuman(key) {
                    var d = fromDateKey(key);
                    if (!d) {
                        return key;
                    }
                    return dayNames[d.getDay()] + ', ' + d.getDate() + ' ' + monthNames[d.getMonth()] + ' ' + d.getFullYear();
                }

                var eventsByDate = {};
                for (var i = 0; i < events.length; i++) {
                    var item = events[i];
                    var key = String(item.event_date || '');
                    if (!key) {
                        continue;
                    }
                    if (!eventsByDate[key]) {
                        eventsByDate[key] = [];
                    }
                    eventsByDate[key].push(item);
                }

                function setWeekdays() {
                    weekdaysEl.innerHTML = '';
                    for (var i = 0; i < dayNames.length; i++) {
                        var el = document.createElement('div');
                        el.className = 'calendar-weekday';
                        el.textContent = dayNames[i];
                        weekdaysEl.appendChild(el);
                    }
                }

                function showTooltip(dateKey, x, y) {
                    var list = eventsByDate[dateKey] || [];
                    if (!list.length || !tooltipEl) {
                        return;
                    }
                    var html = '<div><strong>' + formatDateHuman(dateKey) + '</strong></div>';
                    var max = Math.min(list.length, 5);
                    for (var i = 0; i < max; i++) {
                        html += '<div style="margin-top:4px;">#' + String(list[i].booking_ref || '') + ' - ' + String(list[i].event_type || 'Event') + '</div>';
                    }
                    if (list.length > max) {
                        html += '<div class="muted" style="margin-top:4px;">+' + (list.length - max) + ' more</div>';
                    }
                    tooltipEl.innerHTML = html;
                    tooltipEl.style.display = 'block';
                    tooltipEl.style.left = (x + 12) + 'px';
                    tooltipEl.style.top = (y + 12) + 'px';
                }

                function hideTooltip() {
                    if (tooltipEl) {
                        tooltipEl.style.display = 'none';
                    }
                }

                function selectDate(dateKey) {
                    state.selectedDate = dateKey;
                    renderDayEvents();
                    render();
                }

                function renderDayEvents() {
                    dayEventsBody.innerHTML = '';
                    var dateKey = state.selectedDate;
                    if (!dateKey || !eventsByDate[dateKey] || !eventsByDate[dateKey].length) {
                        dayEventsTitle.textContent = 'Events';
                        var emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = '<td colspan="' + (isDirector ? '6' : '5') + '" class="muted">Select a day with events.</td>';
                        dayEventsBody.appendChild(emptyRow);
                        return;
                    }

                    dayEventsTitle.textContent = 'Events on ' + formatDateHuman(dateKey);
                    var rows = eventsByDate[dateKey];
                    for (var i = 0; i < rows.length; i++) {
                        var row = document.createElement('tr');
                        var html = '';
                        if (isDirector) {
                            html += '<td>' + String(rows[i].business_name || '-') + '</td>';
                        }
                        html += '<td>' + String(rows[i].booking_ref || '-') + '</td>';
                        html += '<td>' + String(rows[i].customer_name || '-') + '</td>';
                        html += '<td>' + String(rows[i].event_type || '-') + '</td>';
                        html += '<td>' + String(rows[i].event_location || '-') + '</td>';
                        html += '<td>' + String(rows[i].status || '-') + '</td>';
                        row.innerHTML = html;
                        dayEventsBody.appendChild(row);
                    }
                }

                function bindDayCell(cell, dateKey) {
                    var hasEvents = !!(eventsByDate[dateKey] && eventsByDate[dateKey].length);
                    if (!hasEvents) {
                        return;
                    }
                    cell.classList.add('has-events');
                    cell.addEventListener('mouseenter', function (event) { showTooltip(dateKey, event.clientX, event.clientY); });
                    cell.addEventListener('mousemove', function (event) { showTooltip(dateKey, event.clientX, event.clientY); });
                    cell.addEventListener('mouseleave', hideTooltip);
                    cell.addEventListener('click', function () { selectDate(dateKey); });
                }

                function renderMonth() {
                    setWeekdays();
                    viewEl.innerHTML = '';
                    var y = state.cursor.getFullYear();
                    var m = state.cursor.getMonth();
                    titleEl.textContent = monthNames[m] + ' ' + y;

                    var first = new Date(y, m, 1);
                    var start = new Date(y, m, 1 - first.getDay());
                    var grid = document.createElement('div');
                    grid.className = 'calendar-grid';

                    for (var i = 0; i < 42; i++) {
                        var day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
                        var key = toDateKey(day);
                        var count = (eventsByDate[key] || []).length;

                        var cell = document.createElement('div');
                        cell.className = 'calendar-day' + (day.getMonth() !== m ? ' outside' : '') + (state.selectedDate === key ? ' selected' : '');
                        cell.innerHTML = '<div class="day-num">' + day.getDate() + '</div>' + (count ? '<span class="event-dot">' + count + '</span>' : '');

                        bindDayCell(cell, key);
                        grid.appendChild(cell);
                    }

                    viewEl.appendChild(grid);
                }

                function renderWeek() {
                    setWeekdays();
                    viewEl.innerHTML = '';
                    var cursor = new Date(state.cursor.getFullYear(), state.cursor.getMonth(), state.cursor.getDate());
                    var weekStart = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() - cursor.getDay());
                    var weekEnd = new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + 6);
                    titleEl.textContent = formatDateHuman(toDateKey(weekStart)) + ' - ' + formatDateHuman(toDateKey(weekEnd));

                    var grid = document.createElement('div');
                    grid.className = 'week-grid';

                    for (var i = 0; i < 7; i++) {
                        var day = new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + i);
                        var key = toDateKey(day);
                        var list = eventsByDate[key] || [];

                        var cell = document.createElement('div');
                        cell.className = 'week-day' + (state.selectedDate === key ? ' selected' : '');
                        var html = '<div class="day-num">' + day.getDate() + ' ' + monthNames[day.getMonth()].slice(0, 3) + '</div>';
                        if (list.length) {
                            html += '<div style="margin-top:6px; font-size:12px;">' + list.length + ' event(s)</div>';
                        } else {
                            html += '<div class="muted" style="margin-top:6px; font-size:12px;">No events</div>';
                        }
                        cell.innerHTML = html;
                        bindDayCell(cell, key);
                        grid.appendChild(cell);
                    }

                    viewEl.appendChild(grid);
                }

                function renderYear() {
                    setWeekdays();
                    viewEl.innerHTML = '';
                    var y = state.cursor.getFullYear();
                    titleEl.textContent = String(y);

                    var yearGrid = document.createElement('div');
                    yearGrid.className = 'year-grid';

                    for (var m = 0; m < 12; m++) {
                        var box = document.createElement('div');
                        box.className = 'year-month';
                        box.innerHTML = '<div class="year-month-title">' + monthNames[m] + '</div>';

                        var days = document.createElement('div');
                        days.className = 'year-days';

                        var first = new Date(y, m, 1);
                        var lead = first.getDay();
                        for (var l = 0; l < lead; l++) {
                            var padCell = document.createElement('div');
                            padCell.className = 'year-day';
                            days.appendChild(padCell);
                        }

                        var daysInMonth = new Date(y, m + 1, 0).getDate();
                        for (var d = 1; d <= daysInMonth; d++) {
                            var date = new Date(y, m, d);
                            var key = toDateKey(date);
                            var has = !!(eventsByDate[key] && eventsByDate[key].length);
                            var dayCell = document.createElement('div');
                            dayCell.className = 'year-day' + (has ? ' has-events' : '') + (state.selectedDate === key ? ' selected' : '');
                            dayCell.textContent = String(d);
                            if (has) {
                                bindDayCell(dayCell, key);
                            }
                            days.appendChild(dayCell);
                        }

                        box.appendChild(days);
                        yearGrid.appendChild(box);
                    }

                    viewEl.appendChild(yearGrid);
                }

                function render() {
                    hideTooltip();
                    for (var i = 0; i < modeBtns.length; i++) {
                        modeBtns[i].classList.toggle('active', modeBtns[i].getAttribute('data-cal-mode') === state.mode);
                    }
                    if (state.mode === 'week') {
                        renderWeek();
                    } else if (state.mode === 'year') {
                        renderYear();
                    } else {
                        renderMonth();
                    }
                }

                function shift(direction) {
                    if (state.mode === 'week') {
                        state.cursor = new Date(state.cursor.getFullYear(), state.cursor.getMonth(), state.cursor.getDate() + (7 * direction));
                    } else if (state.mode === 'year') {
                        state.cursor = new Date(state.cursor.getFullYear() + direction, state.cursor.getMonth(), 1);
                    } else {
                        state.cursor = new Date(state.cursor.getFullYear(), state.cursor.getMonth() + direction, 1);
                    }
                    render();
                }

                if (prevBtn) {
                    prevBtn.addEventListener('click', function () { shift(-1); });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', function () { shift(1); });
                }
                for (var i = 0; i < modeBtns.length; i++) {
                    modeBtns[i].addEventListener('click', function () {
                        state.mode = this.getAttribute('data-cal-mode') || 'month';
                        render();
                    });
                }

                var todayKey = toDateKey(new Date());
                if (eventsByDate[todayKey] && eventsByDate[todayKey].length) {
                    state.selectedDate = todayKey;
                } else {
                    var keys = Object.keys(eventsByDate).sort();
                    state.selectedDate = keys.length ? keys[0] : '';
                }

                render();
                renderDayEvents();
            })();
        </script>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
