@extends('layouts.crm_client_detail')
@section('title', 'Visa Expiry Reports')

@section('content')

<style>
/* FullCalendar v6 minimal styles */
.fc {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 1em;
}
.fc-header-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}
.fc-button {
    padding: 5px 10px;
    margin: 0 2px;
    border: 1px solid #ddd;
    background: #fff;
    cursor: pointer;
}
.fc-button:hover {
    background: #f0f0f0;
}
.fc-button-primary {
    background: #3788d8;
    color: white;
    border-color: #3788d8;
}
.fc-button-primary:hover {
    background: #2a6bb0;
}
.fc-daygrid-day {
    cursor: pointer;
}
.fc-event {
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 3px;
    margin-bottom: 2px;
}
.fc-event-container .fc-h-event {
    cursor: pointer;
}
.fc-event a.fc-event-title-link {
    color: inherit;
    text-decoration: none;
    cursor: pointer;
}
.fc-event a.fc-event-title-link:hover {
    text-decoration: underline;
    color: inherit;
}
.fc-more-popover {
    overflow-y: scroll;
    max-height: 50%;
    max-width: auto;
}
.visa-expiry-legend {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 3px;
}
.legend-color.expired {
    background-color: #dc3545;
}
.legend-color.expiring-soon {
    background-color: #ffc107;
}
.legend-color.future {
    background-color: #3788d8;
}
</style>

<div class="section-body">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>
                        @icon('fa-calendar-alt', ['class' => 'mr-2'])
                        Visa Expiry Reports
                    </h4>
                    <div class="card-header-action">
                        <button onclick="location.reload()" class="btn btn-sm btn-info">
                            @icon('fa-sync') Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Legend -->
                    <div class="visa-expiry-legend">
                        <div class="legend-item">
                            <div class="legend-color expired"></div>
                            <span>Expired</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color expiring-soon"></div>
                            <span>Expiring within 7 days</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color future"></div>
                            <span>Future expiry</span>
                        </div>
                    </div>

                    <!-- Calendar -->
                    <div class="fc-overflow">
                        <div id="visaExpiryCalendar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" tabindex="-1" data-bs-backdrop="static" id="event-details-modal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header rounded-0">
                <h5 class="modal-title">Visa Expiry Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">@icon('fa-times')</button>
            </div>
            <div class="modal-body rounded-0">
                <div class="container-fluid">
                    <dl>
                        <dt class="text-muted">Client Name</dt>
                        <dd id="modal-title" class="fw-bold fs-4"></dd>
                       
                        <dt class="text-muted">Expiry Date</dt>
                        <dd id="modal-start" class=""></dd>

                        <dt class="text-muted">Visa Country</dt>
                        <dd id="modal-country" class=""></dd>

                        <dt class="text-muted">Visa Type</dt>
                        <dd id="modal-type" class=""></dd>

                        <dt class="text-muted">Days Until Expiry</dt>
                        <dd id="modal-days" class=""></dd>
                    </dl>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="modal-view-client">View Client</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
{{-- Load FullCalendar v6 from CDN --}}
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"></script>
<script>
// Wait for FullCalendar to fully load
(function checkFullCalendar() {
    if (typeof FullCalendar !== 'undefined' && FullCalendar.Calendar) {
        initializeCalendar();
    } else {
        setTimeout(checkFullCalendar, 50);
    }
})();

function initializeCalendar() {
    var events = [];
    var scheds = {!! json_encode($sched_res, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    
    // Debug logging
    console.log('FullCalendar v6 Initialization:');
    console.log('scheds data:', scheds);
    console.log('scheds type:', typeof scheds);
    console.log('scheds keys:', Object.keys(scheds));
    
    if (!!scheds && typeof scheds === 'object') {
        Object.keys(scheds).map(k => {
            var row = scheds[k];
            events.push({ 
                id: row.id, 
                title: row.stitle, 
                start: row.startdate, 
                end: row.end,
                backgroundColor: row.color || '#3788d8',
                borderColor: row.color || '#3788d8',
                extendedProps: {
                    client_id: row.client_id,
                    url: row.url,
                    visa_country: row.visa_country,
                    visa_type: row.visa_type,
                    days_until_expiry: row.days_until_expiry,
                    displayDate: row.displayDate
                }
            });
        });
    }
    
    console.log('Events array:', events);
    console.log('Events count:', events.length);

    var calendarEl = document.getElementById('visaExpiryCalendar');
    if (!calendarEl) {
        console.error('Calendar element #visaExpiryCalendar not found');
        return;
    }

    try {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            height: "auto",
            initialView: "dayGridMonth",
            editable: false,
            selectable: true,
            dayMaxEvents: true,
            moreLinkText: "More",
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth",
            },
            events: events,
            eventContent: function(arg) {
                var url = arg.event.extendedProps.url || '#';
                var title = arg.event.title;
                return {
                    html: '<a href="' + url + '" class="fc-event-title-link" title="View client profile">' + title + '</a>'
                };
            },
            eventClick: function(info) {
                if (info.jsEvent.target.closest && info.jsEvent.target.closest('a.fc-event-title-link')) {
                    return; // Let the link open the client profile
                }
                console.log('Event clicked:', info);
                var details = document.getElementById('event-details-modal');
                if (!details) return;
                
                var eventData = info.event.extendedProps;
                var id = info.event.id;

                if (!!scheds[id]) {
                    var titleEl = details.querySelector('#modal-title');
                    var startEl = details.querySelector('#modal-start');
                    var countryEl = details.querySelector('#modal-country');
                    var typeEl = details.querySelector('#modal-type');
                    var daysEl = details.querySelector('#modal-days');
                    var viewClientBtn = details.querySelector('#modal-view-client');

                    if (titleEl) titleEl.textContent = scheds[id].stitle;
                    if (startEl) startEl.textContent = scheds[id].displayDate || scheds[id].startdate;
                    if (countryEl) countryEl.textContent = scheds[id].visa_country || 'N/A';
                    if (typeEl) typeEl.textContent = scheds[id].visa_type || 'N/A';
                    
                    var daysUntil = scheds[id].days_until_expiry;
                    if (daysUntil < 0) {
                        if (daysEl) daysEl.innerHTML = '<span class="text-danger fw-bold">Expired ' + Math.abs(daysUntil) + ' day(s) ago</span>';
                    } else if (daysUntil === 0) {
                        if (daysEl) daysEl.innerHTML = '<span class="text-danger fw-bold">Expires TODAY!</span>';
                    } else if (daysUntil <= 7) {
                        if (daysEl) daysEl.innerHTML = '<span class="text-warning fw-bold">' + daysUntil + ' day(s) remaining</span>';
                    } else {
                        if (daysEl) daysEl.innerHTML = '<span class="text-info">' + daysUntil + ' day(s) remaining</span>';
                    }

                    if (viewClientBtn && scheds[id].url) {
                        viewClientBtn.onclick = function() {
                            window.open(scheds[id].url, "_blank");
                        };
                    }

                    // Show modal using Bootstrap 5
                    var modal = new bootstrap.Modal(details);
                    modal.show();
                } else {
                    alert("Event data not found");
                }
            }
        });

        calendar.render();
        console.log('Calendar rendered successfully');
    } catch (error) {
        console.error('Error initializing calendar:', error);
    }
}
</script>
@endsection
