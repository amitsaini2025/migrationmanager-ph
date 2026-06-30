@props(['case'])

@php
    $client = $case->client;
    $lastUpdated = new DateTime($case->updated_at);
    $today = new DateTime();
    $interval = $today->diff($lastUpdated);
    $daysStalled = $interval->days;
    
    // Safety check for null client
    if (!$client) {
        $client = (object) [
            'id' => null,
            'first_name' => null,
            'last_name' => null,
            'client_id' => null
        ];
    }
    
    if ($daysStalled < 1) {
        $daysStalledText = 'Today';
    } else {
        $daysStalledText = $daysStalled . ' days ago';
    }
    
    $daysStalledClass = $daysStalled > 14 ? 'text-danger' : ($daysStalled > 7 ? 'text-warning' : 'text-info');
    
    // Get matter name
    if ($case->sel_matter_id == 1) {
        $matter_name = 'General matter';
    } else {
        $matter = $case->matter ?? null;
        $matter_name = $matter ? $matter->title : 'NA';
    }
    
    // Get latest activity information
    $latestActivity = $case->latest_activity ?? ['type' => 'default', 'date' => $case->updated_at];
    $activityType = $latestActivity['type'];
    
    $activityConfig = [
        'signed' => [
            'label' => 'Document Signed',
            'icon' => 'file-signature',
            'class' => 'activity-signed',
            'color' => '#28a745'
        ],
        'document_uploaded' => [
            'label' => 'Document Uploaded',
            'icon' => 'upload',
            'class' => 'activity-upload',
            'color' => '#007bff'
        ],
        'note_added' => [
            'label' => 'Note Added',
            'icon' => 'sticky-note',
            'class' => 'activity-note',
            'color' => '#ffc107'
        ],
        'email_sent' => [
            'label' => 'Email Sent',
            'icon' => 'mail',
            'class' => 'activity-email',
            'color' => '#17a2b8'
        ],
        'sms_sent' => [
            'label' => 'SMS Sent',
            'icon' => 'message-square-text',
            'class' => 'activity-sms',
            'color' => '#00bcd4'
        ],
        'status_changed' => [
            'label' => 'Status Changed',
            'icon' => 'arrow-left-right',
            'class' => 'activity-status',
            'color' => '#6f42c1'
        ],
        'stage_updated' => [
            'label' => 'Stage Updated',
            'icon' => 'list-todo',
            'class' => 'activity-stage',
            'color' => '#fd7e14'
        ],
        'appointment_scheduled' => [
            'label' => 'Appointment Set',
            'icon' => 'calendar-check',
            'class' => 'activity-appointment',
            'color' => '#20c997'
        ],
        'payment_received' => [
            'label' => 'Payment Received',
            'icon' => 'dollar-sign',
            'class' => 'activity-payment',
            'color' => '#28a745'
        ],
        'default' => [
            'label' => 'Recently Updated',
            'icon' => 'clock',
            'class' => 'activity-default',
            'color' => '#6c757d'
        ]
    ];
    
    $activity = $activityConfig[$activityType] ?? $activityConfig['default'];
@endphp

<li>
    <div class="case-details">
        <span class="client-name">
            {{ $client->first_name ?: config('constants.empty') }} {{ $client->last_name ?: config('constants.empty') }}
            (<a href="{{ route('clients.detail', [base64_encode(convert_uuencode($client->id)), $case->client_unique_matter_no]) }}">
                {{ $client->client_id ?: config('constants.empty') }}
            </a>)
        </span>
        <span class="case-info">
            <a href="{{ route('clients.detail', [base64_encode(convert_uuencode($client->id)), $case->client_unique_matter_no]) }}">
                {{ $matter_name }} ({{ $case->client_unique_matter_no }})
            </a>
            <span style="display: inline-block;" class="stalled-days {{ $daysStalledClass }}">
                ({{ $daysStalledText }})
            </span>
        </span>
    </div>
    <div class="case-activity-badge {{ $activity['class'] }}">
        @icon($activity['icon'], ['class' => 'icon-sm'])
        <span class="activity-label">{{ $activity['label'] }}</span>
    </div>
</li>

<style>
.case-activity-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8em;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.2s ease;
    border: 2px solid;
}

.case-activity-badge i,
.case-activity-badge svg.lucide {
    font-size: 1em;
}

/* Activity Type Colors */
.activity-signed {
    background: #d4edda;
    color: #155724;
    border-color: #28a745;
}

.activity-upload {
    background: #cce5ff;
    color: #004085;
    border-color: #007bff;
}

.activity-note {
    background: #fff3cd;
    color: #856404;
    border-color: #ffc107;
}

.activity-email {
    background: #d1ecf1;
    color: #0c5460;
    border-color: #17a2b8;
}

.activity-status {
    background: #e2d9f3;
    color: #3d1d6b;
    border-color: #6f42c1;
}

.activity-stage {
    background: #ffe5d0;
    color: #7a3d00;
    border-color: #fd7e14;
}

.activity-appointment {
    background: #d4f4dd;
    color: #0a4d1d;
    border-color: #20c997;
}

.activity-payment {
    background: #d4edda;
    color: #155724;
    border-color: #28a745;
}

.activity-sms {
    background: #d0f4f7;
    color: #006978;
    border-color: #00bcd4;
}

.activity-default {
    background: #e9ecef;
    color: #495057;
    border-color: #6c757d;
}

/* Hover effect */
.case-activity-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Mobile responsive */
@media (max-width: 768px) {
    .case-activity-badge {
        font-size: 0.75em;
        padding: 4px 8px;
    }
    
    .activity-label {
        display: none;
    }
    
    .case-activity-badge i,
    .case-activity-badge svg.lucide {
        font-size: 1.2em;
    }
}
</style>
