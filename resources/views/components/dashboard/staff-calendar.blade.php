@props([
    'stats' => ['today' => 0, 'this_week' => 0, 'upcoming' => 0],
    'timezone' => config('app.timezone'),
    'calendarTypes' => [],
    'defaultType' => 'paid',
])

<section class="dashboard-calendar-section" id="myCalendarSection" aria-label="Appointment calendar">
    <div class="dashboard-calendar-card">
        <div class="dashboard-calendar-header">
            <div class="dashboard-calendar-header-left">
                <h2>
                    @icon('fa-calendar-days')
                    Calendar
                </h2>
                <p class="dashboard-calendar-subtitle">Website bookings only. Choose a calendar to view its appointments.</p>
            </div>
            <div class="dashboard-calendar-header-right">
                <div class="dashboard-calendar-stats">
                    <div class="dashboard-cal-stat dashboard-cal-stat--today" title="Appointments today">
                        <span class="dashboard-cal-stat-value" id="calStatToday">{{ $stats['today'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">Today</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--week" title="Appointments this week">
                        <span class="dashboard-cal-stat-value" id="calStatWeek">{{ $stats['this_week'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">This week</span>
                    </div>
                    <div class="dashboard-cal-stat dashboard-cal-stat--upcoming" title="Upcoming appointments">
                        <span class="dashboard-cal-stat-value" id="calStatUpcoming">{{ $stats['upcoming'] ?? 0 }}</span>
                        <span class="dashboard-cal-stat-label">Upcoming</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-calendar-types" role="tablist" aria-label="Calendar type">
            @foreach($calendarTypes as $calendarType)
                <button
                    type="button"
                    class="dashboard-cal-type-btn{{ ($calendarType['key'] ?? '') === $defaultType ? ' is-active' : '' }}"
                    data-calendar-type="{{ $calendarType['key'] }}"
                    role="tab"
                    aria-selected="{{ ($calendarType['key'] ?? '') === $defaultType ? 'true' : 'false' }}"
                >{{ $calendarType['label'] }}</button>
            @endforeach
        </div>

        <div class="dashboard-calendar-legend" aria-label="Appointment status colours">
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--pending"></span> Pending</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--paid"></span> Paid</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--confirmed"></span> Confirmed</span>
            <span class="dashboard-cal-legend-item"><span class="dashboard-cal-dot dashboard-cal-dot--completed"></span> Completed</span>
        </div>

        <div class="dashboard-calendar-body">
            <div class="dashboard-calendar-wrapper">
                <div
                    id="staffDashboardCalendar"
                    class="dashboard-calendar-container"
                    data-timezone="{{ $timezone }}"
                    data-calendar-type="{{ $defaultType }}"
                ></div>
            </div>

            <div class="dashboard-upcoming-panel" id="dashboardUpcomingPanel">
                <div class="dashboard-upcoming-header">
                    <h3>
                        @icon('fa-list')
                        Appointments
                    </h3>
                    <span class="dashboard-upcoming-count" id="dashboardUpcomingCount">0</span>
                </div>
                <p class="dashboard-upcoming-help">Upcoming appointments for the selected calendar, grouped by date.</p>
                <div class="dashboard-upcoming-list" id="dashboardUpcomingList" aria-live="polite">
                    <div class="dashboard-upcoming-empty">Loading appointments…</div>
                </div>
            </div>
        </div>
    </div>
</section>
