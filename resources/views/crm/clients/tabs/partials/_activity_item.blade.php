@php
    $admin = $admin ?? ($activity->staff ?? $activity->creator ?? null);
@endphp
@php
    // Determine note type for specific styling
    $noteTypeClass = '';
    $noteIcon = 'fa-sticky-note';
    
    if($activity->activity_type === 'note') {
        $subject = strtolower($activity->subject ?? '');
        
        if(str_contains($subject, 'call')) {
            $noteTypeClass = 'activity-type-note-call';
            $noteIcon = 'fa-phone';
        } elseif(str_contains($subject, 'email')) {
            $noteTypeClass = 'activity-type-note-email';
            $noteIcon = 'fa-envelope';
        } elseif(str_contains($subject, 'in-person')) {
            $noteTypeClass = 'activity-type-note-in-person';
            $noteIcon = 'fa-user-friends';
        } elseif(str_contains($subject, 'attention')) {
            $noteTypeClass = 'activity-type-note-attention';
            $noteIcon = 'fa-exclamation-triangle';
        } elseif(str_contains($subject, 'others')) {
            $noteTypeClass = 'activity-type-note-others';
            $noteIcon = 'fa-ellipsis-h';
        } else {
            $noteTypeClass = 'activity-type-note';
            $noteIcon = 'fa-sticky-note';
        }
    }
@endphp
@php
    $feedStaffDisplay = 'NA';
    if ($admin) {
        $feedStaffDisplay = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? ''));
        if ($feedStaffDisplay === '') {
            $feedStaffDisplay = 'NA';
        }
    }
@endphp
@php
    $legacyActivityTypeClass = '';
    $subj = $activity->subject ?? '';
    if (empty($activity->activity_type) && $subj !== '') {
        $subjLower = strtolower($subj);
        if (str_contains($subjLower, 'uploaded email:')) {
            $legacyActivityTypeClass = 'activity-type-email';
        } elseif (str_contains($subjLower, 'invoice') || str_contains($subjLower, 'receipt') || str_contains($subjLower, 'ledger') || str_contains($subjLower, 'payment') || str_contains($subjLower, 'account')) {
            $legacyActivityTypeClass = 'activity-type-financial';
        } elseif (str_contains($subjLower, 'document') && !preg_match('/(receipt document|journal receipt document|client receipt document|office receipt document)/i', $subjLower)) {
            $legacyActivityTypeClass = 'activity-type-document';
        }
    }
@endphp

<li class="feed-item feed-item--{{ $activity->activity_type === 'stage' ? 'stage' : 'email' }} activity {{ $activity->activity_type ? 'activity-type-' . $activity->activity_type : $legacyActivityTypeClass }} {{ $noteTypeClass }}" id="activity_{{ $activity->id }}" data-created-at="{{ $activity->created_at ? \Carbon\Carbon::parse($activity->created_at)->format('Y-m-d') : '' }}">
    <span class="feed-icon {{ $activity->activity_type === 'sms' ? 'feed-icon-sms' : '' }} {{ $activity->activity_type === 'activity' ? 'feed-icon-activity' : '' }} {{ $activity->activity_type === 'stage' ? 'feed-icon-stage' : '' }} {{ $activity->activity_type === 'financial' || $legacyActivityTypeClass === 'activity-type-financial' ? 'feed-icon-financial' : '' }} {{ $activity->activity_type === 'email' || $legacyActivityTypeClass === 'activity-type-email' ? 'feed-icon-email' : '' }} {{ $activity->activity_type === 'signature' ? 'feed-icon-signature' : '' }} {{ $activity->activity_type === 'note' ? 'feed-icon-note ' . str_replace('activity-type-', 'feed-icon-', $noteTypeClass) : '' }}">
        @if($activity->activity_type === 'sms')
            @icon('fa-sms')
        @elseif($activity->activity_type === 'note')
            @icon($noteIcon)
        @elseif($activity->activity_type === 'activity')
            @icon('fa-bolt')
        @elseif($activity->activity_type === 'stage')
            @icon('fa-route')
        @elseif($activity->activity_type === 'financial')
            @icon('fa-dollar-sign')
        @elseif($activity->activity_type === 'email')
            @icon('fa-envelope')
        @elseif($activity->activity_type === 'signature')
            @icon('fa-file-signature')
        @elseif($activity->activity_type === 'document')
            @icon('fa-file-alt')
        @elseif(str_contains(strtolower($activity->subject ?? ''), 'uploaded email:'))
            @icon('fa-envelope')
        @elseif(str_contains(strtolower($activity->subject ?? ''), "invoice") || 
                str_contains(strtolower($activity->subject ?? ''), "receipt") || 
                str_contains(strtolower($activity->subject ?? ''), "ledger") || 
                str_contains(strtolower($activity->subject ?? ''), "payment") ||
                str_contains(strtolower($activity->subject ?? ''), "account"))
            @icon('fa-dollar-sign')
        @elseif(str_contains($activity->subject ?? '', "document"))
            @icon('fa-file-alt')
        @else
            @icon('fa-sticky-note')
        @endif
    </span>
    <div class="feed-content">
        @if($activity->activity_type === 'stage')
            <div class="feed-item-stage">
                <div class="feed-item-stage-header">
                    <span class="feed-item-staff">{{ $feedStaffDisplay }}</span>
                    <span class="feed-timestamp">{{ date('d M Y, H:i', strtotime($activity->created_at)) }}</span>
                </div>
                <div class="feed-item-stage-body">
                    {!! \App\Support\NoteDescriptionHtml::forDisplay($activity->description ?? '') !!}
                </div>
            </div>
        @else
            <p>
                @if(\App\Models\ActivitiesLog::displaySubjectWithoutStaffPrefix($activity->activity_type ?? null, $activity->subject ?? null))
                <strong>{{ $activity->subject ?? '' }}</strong>
                @else
                <strong>{{ $feedStaffDisplay }}  {{ $activity->subject ?? '' }}</strong>
                @endif
                @if(str_contains($activity->subject ?? '', 'added a note') || str_contains($activity->subject ?? '', 'updated a note'))
                    <span class="convert-activity-to-note"
                       style="margin-left: 5px; cursor: pointer;"
                       title="Convert to Note"
                       data-activity-id="{{ $activity->id }}"
                       data-activity-subject="{{ $activity->subject }}"
                       data-activity-description="{{ $activity->description }}"
                       data-activity-created-by="{{ $activity->created_by }}"
                       data-activity-created-at="{{ $activity->created_at }}"
                       data-client-id="{{ $clientId }}">@icon('fa-ellipsis-v')</span>
                @endif
                -
                @if($activity->description != '')
                    {!! \App\Support\NoteDescriptionHtml::forDisplay($activity->description) !!}
                @endif
            </p>
            <span class="feed-timestamp">{{ date('d M Y, H:i A', strtotime($activity->created_at)) }}</span>
        @endif
    </div>
</li>

