@extends('layouts.crm_client_detail')
@section('title', 'Staff Workload')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card">
                        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                            <h4 class="mb-0">Staff Workload</h4>
                            <form method="get" action="{{ route('adminconsole.staff.workload') }}" class="form-inline">
                                <label for="workload_date" class="mr-2 mb-0">Date</label>
                                <input type="date" name="date" id="workload_date" class="form-control form-control-sm mr-2" value="{{ $selectedDate }}">
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </form>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">{{ $dateLabel }} ({{ config('app.timezone') }}) — active staff only. Pending is open queue as of today.</p>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Staff</th>
                                            <th>Completed<br><small>excl Call</small></th>
                                            <th>Updated</th>
                                            <th>Pending</th>
                                            <th>Call done</th>
                                            <th>Call notes</th>
                                            <th>In-person</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rows as $row)
                                            <tr>
                                                <td>{{ $row['name'] }}</td>
                                                @foreach(['completed_excl_call', 'updated', 'pending', 'call_completed', 'call_notes', 'in_person'] as $key)
                                                    @php $cell = $row[$key] ?? []; @endphp
                                                    <td>
                                                        <strong>{{ $cell['total'] ?? 0 }}</strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            {{ $cell['clients'] ?? 0 }}c · {{ $cell['leads'] ?? 0 }}l
                                                            @if(($cell['personal'] ?? 0) > 0) · {{ $cell['personal'] }}p @endif
                                                        </small>
                                                        @if(($cell['new'] ?? 0) > 0 || ($cell['returning'] ?? 0) > 0)
                                                            <br>
                                                            <small>
                                                                @if(($cell['new'] ?? 0) > 0)<span class="text-primary">{{ $cell['new'] }} new</span>@endif
                                                                @if(($cell['returning'] ?? 0) > 0)<span class="text-warning">{{ $cell['returning'] }} ret</span>@endif
                                                            </small>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No active staff found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
