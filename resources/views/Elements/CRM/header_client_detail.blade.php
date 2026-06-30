<nav class="main-topbar">
    <button class="topbar-toggle" title="Show menu" aria-label="Toggle topbar">
        @icon('fa-ellipsis-h')
    </button>
    <div class="topbar-left">
        <div class="icon-group">
            <a href="{{route('dashboard')}}" class="icon-btn" title="Dashboard">@icon('fa-tachometer-alt')</a>
            <a href="{{ route('signatures.index') }}" class="icon-btn" title="Signature Dashboard">@icon('fa-pen')</a>
            <div class="icon-dropdown js-dropdown">
                <a href="{{ route('booking.appointments.index') }}" class="icon-btn" title="Website Bookings" style="position: relative;">
                    @icon('fa-globe')
                    @php
                        $pendingCount = \App\Models\BookingAppointment::where('status', 'pending')->where('is_paid', 1)->count();
                    @endphp
                    @if($pendingCount > 0)
                        <span class="badge badge-danger" style="position: absolute; top: -5px; right: -5px; font-size: 10px; padding: 2px 5px; border-radius: 10px;">{{ $pendingCount }}</span>
                    @endif
                </a>
                <div class="icon-dropdown-menu">
                    <a class="dropdown-item" href="{{ route('booking.appointments.index') }}">
                        @icon('fa-list', ['class' => 'mr-2']) All Bookings
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.index', ['status' => 'pending']) }}">
                        @icon('fa-clock', ['class' => 'mr-2']) Payment Pending
                        @if($pendingCount > 0)
                            <span class="badge badge-warning ml-1">{{ $pendingCount }}</span>
                        @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'paid']) }}">
                        @icon('fa-calendar-check', ['class' => 'mr-2']) Employer Sponsored Calendar
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'jrp']) }}">
                        @icon('fa-calendar', ['class' => 'mr-2']) JRP Calendar
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'education']) }}">
                        @icon('fa-graduation-cap', ['class' => 'mr-2']) Education
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'tourist']) }}">
                        @icon('fa-plane', ['class' => 'mr-2']) Tourist Visa
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'adelaide']) }}">
                        @icon('fa-city', ['class' => 'mr-2']) Adelaide
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'adelaide_education']) }}">
                        @icon('fa-graduation-cap', ['class' => 'mr-2']) Adelaide Education
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'ajay']) }}">
                        @icon('fa-calendar-alt', ['class' => 'mr-2']) Ajay Calendar
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'kunal']) }}">
                        @icon('fa-calendar-alt', ['class' => 'mr-2']) Kunal Calendar
                    </a>
                    <a class="dropdown-item" href="{{ route('booking.appointments.calendar', ['type' => 'arun']) }}">
                        @icon('fa-calendar-alt', ['class' => 'mr-2']) Arun Calendar
                    </a>
                    @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('booking.sync.dashboard') }}">
                        @icon('fa-sync', ['class' => 'mr-2']) Sync Status
                    </a>
                    @endif
                </div>
            </div>
            <a href="{{route('officevisits.waiting')}}" class="icon-btn" title="In Person">@icon('fa-user-check')</a>
            @if(Auth::user() instanceof \App\Models\Staff && Auth::user()->canAccessFrontDeskCheckIn())
            <a href="{{ route('front-desk.checkin.index') }}" class="icon-btn {{ str_starts_with(Route::currentRouteName() ?? '', 'front-desk.checkin') ? 'active' : '' }}" title="Front-Desk Check-In">@icon('fa-clipboard-check')</a>
            @endif
            <a href="{{route('assignee.action')}}" class="icon-btn" title="Action">@icon('fa-tasks')</a>
            <a href="{{ route('notifications.broadcasts.index') }}" class="icon-btn" title="Broadcasts">
                @icon('fa-bullhorn')
            </a>
            <div class="icon-dropdown js-dropdown">
                <a href="{{route('clients.index')}}" class="icon-btn" title="Clients">@icon('fa-users')</a>
                <div class="icon-dropdown-menu">
                    <a class="dropdown-item" href="{{route('clients.index')}}">@icon('fa-list', ['class' => 'mr-2']) Client List</a>
                    <a class="dropdown-item" href="{{route('clients.clientsmatterslist')}}">@icon('fa-folder-open', ['class' => 'mr-2']) Matter List</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{route('email.smart-import.index')}}">@icon('fa-mail-bulk', ['class' => 'mr-2']) Smart Email Import</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{route('leads.index')}}">@icon('fa-list-alt', ['class' => 'mr-2']) Lead List</a>
                    <a class="dropdown-item" href="{{route('leads.create')}}">@icon('fa-plus-circle', ['class' => 'mr-2']) Add Lead</a>
                </div>
            </div>
            <div class="icon-dropdown js-dropdown">
                <a href="{{route('clients.invoicelist')}}" class="icon-btn" title="Accounts">@icon('fa-briefcase')</a>
                <div class="icon-dropdown-menu">
                    @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                    <a class="dropdown-item" href="{{route('clients.analytics-dashboard')}}" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); font-weight: 600;">@icon('fa-chart-line', ['class' => 'mr-2', 'style' => 'color: #667eea;']) Analytics Dashboard</a>
                    <div class="dropdown-divider"></div>
                    @endif
                    <a class="dropdown-item" href="{{route('clients.clientreceiptlist')}}">@icon('fa-receipt', ['class' => 'mr-2']) Client Receipts</a>
                    <a class="dropdown-item" href="{{route('clients.invoicelist')}}">@icon('fa-file-invoice-dollar', ['class' => 'mr-2']) Invoice Lists</a>
                    <a class="dropdown-item" href="{{route('clients.officereceiptlist')}}">@icon('fa-building', ['class' => 'mr-2']) Office Receipts</a>
                    <a class="dropdown-item" href="{{route('clients.journalreceiptlist')}}">@icon('fa-book', ['class' => 'mr-2']) Journal Receipts</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{route('reports.visaexpires')}}">@icon('fa-calendar-times', ['class' => 'mr-2']) Visa Expiry Report</a>
                </div>
            </div>
            @php
                $u = Auth::user();
                $visibleCrmSheets = ($u && $u instanceof \App\Models\Staff)
                    ? $u->visibleCrmSheetMenuItems()
                    : [];
                $firstSheetKey = $visibleCrmSheets === [] ? null : array_key_first($visibleCrmSheets);
            @endphp
            @if($firstSheetKey !== null)
            <div class="icon-dropdown js-dropdown">
                <a href="{{ \App\Support\CrmSheets::urlForKey($firstSheetKey) }}" class="icon-btn" title="Sheets">@icon('fa-table')</a>
                <div class="icon-dropdown-menu">
                    @foreach($visibleCrmSheets as $vt => $vc)
                    @php
                        $sheetIcon = match (true) {
                            $vt === 'eoi-roi' => 'fa-passport',
                            in_array($vt, ['art', 'art-matters'], true) => 'fa-gavel',
                            default => 'fa-clipboard-list',
                        };
                    @endphp
                    <a class="dropdown-item" href="{{ \App\Support\CrmSheets::urlForKey($vt) }}">@icon($sheetIcon, ['class' => 'mr-2']) {{ $vc }}</a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    <div class="topbar-center">
        <form class="topbar-search">
            @icon('fa-search')
            <select class="form-control js-data-example-ajaxccsearch" type="search" placeholder="Search" aria-label="Search" data-width="320"></select>
        </form>
    </div>
    <div class="topbar-right">
        <a href="javascript:;" title="Add Office Check-In" class="icon-btn opencheckin">@icon('fa-person-booth')</a>
        @if(Auth::user())
            @php
                $notifUnread = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('receiver_status', 0)->count();
            @endphp
            <a href="#" class="icon-btn notification-toggle" title="Notifications">
                @icon('fa-bell')<span class="countbell" id="countbell_notification">{{ $notifUnread > 0 ? $notifUnread : '' }}</span>
            </a>
        @endif
        <div class="profile-dropdown js-dropdown-right">
            <a href="#" class="profile-trigger" id="profile-trigger">
                <img alt="{{ Auth::user() ? Str::limit(Auth::user()->first_name.' '.Auth::user()->last_name, 150, '...') : 'Staff' }}" src="{{ Auth::user() ? Auth::user()->profile_img : asset('img/avatar.png') }}" class="user-img-radious-style"/>
            </a>
            <div class="profile-menu" id="profile-menu">
                <a href="{{route('my_profile')}}">
                    @icon('far fa-user')
                    <span>Profile</span>
                </a>
                @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                <a href="{{route('adminconsole.features.matter.index')}}">
                    @icon('fa-cogs')
                    <span>Admin Console</span>
                </a>
                @endif
                <div class="dropdown-divider"></div>
                <a href="javascript:void(0)" class="text-danger dropdown-item" data-logout="all">
                    @icon('fa-sign-out-alt')
                    <span>Log out everywhere</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<form id="crm-logout-form" action="{{ route('crm.logout') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="id" value="{{ Auth::user() ? Auth::user()->id : '' }}">
</form>
