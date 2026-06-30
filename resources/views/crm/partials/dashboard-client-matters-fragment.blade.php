{{-- Client matters table + pagination only (AJAX fragment). Expects: $data, $filters --}}
<div class="dashboard-matters-meta" data-total="{{ $data->total() }}" hidden></div>
<div class="table-responsive">
    <table class="data-table data-table-enhanced" role="grid">
        <thead>
            <tr role="row">
                <th class="col-matter" role="columnheader">Matter</th>
                <th class="col-client_id" role="columnheader">Client ID</th>
                <th class="col-client_name" role="columnheader">Client Name</th>
                <th class="col-dob" role="columnheader">DOB</th>
                <th class="col-migration_agent" role="columnheader">Migration Agent</th>
                <th class="col-person_responsible" role="columnheader">Person Responsible</th>
                <th class="col-person_assisting" role="columnheader">Person Assisting</th>
                <th class="col-stage" role="columnheader">Stage</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $matter)
                @if($matter && $matter->client_id)
                    <tr role="row" data-matter-id="{{ $matter->id ?? '' }}">
                        <td class="col-matter" style="white-space: initial;">
                            <a href="{{ route('clients.detail', [base64_encode(convert_uuencode($matter->client_id)), $matter->client_unique_matter_no ?? '']) }}" class="matter-link">
                                @if($matter->sel_matter_id == 1)
                                    General matter
                                @else
                                    {{ $matter->matter->title ?? 'NA' }}
                                @endif
                                ({{ $matter->client_unique_matter_no ?? 'N/A' }})
                            </a>
                            @php
                                $emailCount = (int) ($matter->dashboard_unread_mail_count ?? 0);
                            @endphp
                            @if($emailCount > 0)
                                <span class="badge badge-email" title="{{ $emailCount }} unread emails">
                                    @icon('mail', ['class' => 'icon-sm']) {{ $emailCount }}
                                </span>
                            @endif
                        </td>
                        <td class="col-client_id">
                            @php
                                $clientDetailParams = [];
                                if($matter && $matter->client_id) {
                                    $clientDetailParams = [base64_encode(convert_uuencode($matter->client_id))];
                                    if(!empty($matter->client_unique_matter_no)) {
                                        $clientDetailParams[] = $matter->client_unique_matter_no;
                                    }
                                }
                            @endphp
                            @if(!empty($clientDetailParams))
                                <a href="{{ route('clients.detail', $clientDetailParams) }}" class="client-id-link">
                                    {{ ($matter->client && $matter->client->client_id) ? $matter->client->client_id : config('constants.empty') }}
                                </a>
                            @else
                                <span class="text-muted">{{ config('constants.empty') }}</span>
                            @endif
                        </td>
                        <td class="col-client_name">
                            @if($matter->client)
                                {{ ($matter->client->first_name ?? '') ?: config('constants.empty') }} {{ ($matter->client->last_name ?? '') ?: config('constants.empty') }}
                            @else
                                {{ config('constants.empty') }}
                            @endif
                        </td>
                        <td class="col-dob">
                            @if($matter->client && $matter->client->dob)
                                {{ \Carbon\Carbon::parse($matter->client->dob)->format('d/m/Y') }}
                            @else
                                {{ config('constants.empty') }}
                            @endif
                        </td>
                        <td class="col-migration_agent">
                            @if($matter->migrationAgent)
                                <div class="user-avatar-cell">
                                    <div class="avatar-sm">
                                        {{ substr($matter->migrationAgent->first_name, 0, 1) }}{{ substr($matter->migrationAgent->last_name, 0, 1) }}
                                    </div>
                                    {{ $matter->migrationAgent->first_name }} {{ $matter->migrationAgent->last_name }}
                                </div>
                            @else
                                {{ config('constants.empty') }}
                            @endif
                        </td>
                        <td class="col-person_responsible">
                            @if($matter->personResponsible)
                                <div class="user-avatar-cell">
                                    <div class="avatar-sm">
                                        {{ substr($matter->personResponsible->first_name, 0, 1) }}{{ substr($matter->personResponsible->last_name, 0, 1) }}
                                    </div>
                                    {{ $matter->personResponsible->first_name }} {{ $matter->personResponsible->last_name }}
                                </div>
                            @else
                                {{ config('constants.empty') }}
                            @endif
                        </td>
                        <td class="col-person_assisting">
                            @if($matter->personAssisting)
                                <div class="user-avatar-cell">
                                    <div class="avatar-sm">
                                        {{ substr($matter->personAssisting->first_name, 0, 1) }}{{ substr($matter->personAssisting->last_name, 0, 1) }}
                                    </div>
                                    {{ $matter->personAssisting->first_name }} {{ $matter->personAssisting->last_name }}
                                </div>
                            @else
                                {{ config('constants.empty') }}
                            @endif
                        </td>
                        <td class="col-stage">
                            @include('crm.partials.dashboard-matter-stage-link', ['matter' => $matter])
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="8" class="empty-state">
                        <div class="empty-state-modern">
                            @icon('inbox', ['class' => 'empty-state-icon'])
                            <h4>No Records Found</h4>
                            <p>Try adjusting your filters or search criteria.</p>
                            @if(isset($filters['client_name']) || isset($filters['client_stage']))
                                <a href="{{ route('dashboard') }}" class="btn btn-primary mt-3">
                                    @icon('x', ['class' => 'icon-sm']) Clear All Filters
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($data->hasPages())
    <div class="pagination-container">
        <div class="pagination-info">
            Showing <strong>{{ $data->firstItem() ?? 0 }}</strong> to <strong>{{ $data->lastItem() ?? 0 }}</strong> of <strong>{{ $data->total() }}</strong> results
        </div>
        <div class="pagination-links">
            {!! $data->appends(request()->except('page'))->render() !!}
        </div>
    </div>
@endif
