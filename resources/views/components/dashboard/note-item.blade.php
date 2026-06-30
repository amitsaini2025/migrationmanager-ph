@props(['note'])

@php
    $client = $note->client;
    $deadline = new DateTime($note->note_deadline);
    $today = new DateTime();
    $isOverdue = $deadline < $today;
    
    if ($isOverdue) {
        $interval = $today->diff($deadline);
        $daysOverdue = $interval->days;
        $daysLeftText = $daysOverdue . ' day' . ($daysOverdue != 1 ? 's' : '') . ' overdue';
        $daysLeftClass = 'text-danger';
        $urgencyBadge = 'overdue';
        $urgencyLabel = 'OVERDUE';
    } else {
        $interval = $today->diff($deadline);
        $daysLeft = $interval->days;
        
        if ($daysLeft == 0) {
            $daysLeftText = 'Due today';
            $daysLeftClass = 'text-danger';
            $urgencyBadge = 'today';
            $urgencyLabel = 'TODAY';
        } elseif ($daysLeft <= 3) {
            $daysLeftText = $daysLeft . ' day' . ($daysLeft != 1 ? 's' : '') . ' left';
            $daysLeftClass = 'text-danger';
            $urgencyBadge = 'urgent';
            $urgencyLabel = 'URGENT';
        } elseif ($daysLeft <= 7) {
            $daysLeftText = $daysLeft . ' days left';
            $daysLeftClass = 'text-warning';
            $urgencyBadge = 'soon';
            $urgencyLabel = 'SOON';
        } else {
            $daysLeftText = $daysLeft . ' days left';
            $daysLeftClass = 'text-info';
            $urgencyBadge = 'upcoming';
            $urgencyLabel = 'UPCOMING';
        }
    }
    
    // Get assigned user
    $assignedUser = $note->assignedUser ?? null;
    $assignedName = $assignedUser ? $assignedUser->first_name . ' ' . $assignedUser->last_name : 'Unassigned';
@endphp

<li class="note-item-enhanced urgency-{{ $urgencyBadge }}">
    <div class="note-item-header">
        <span class="urgency-badge badge-{{ $urgencyBadge }}">
            @icon($isOverdue ? 'triangle-alert' : 'clock', ['class' => 'icon-sm'])
            {{ $urgencyLabel }}
        </span>
        <span class="deadline-date {{ $daysLeftClass }}">
            @icon('calendar', ['class' => 'icon-sm'])
            {{ $deadline->format('d M Y') }}
        </span>
    </div>
    
    <div class="note-item-body">
        <div class="note-client-info">
            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($client->id))) }}" class="client-link">
                @icon('user', ['class' => 'icon-sm'])
                @php
                    $noteClientLabel = trim($client->company_name_or_personal_name);
                    if ($noteClientLabel === '') {
                        $noteClientLabel = trim(($client->first_name ?: '') . ' ' . ($client->last_name ?: '')) ?: 'Unknown Client';
                    }
                @endphp
                <strong>{{ $noteClientLabel }}</strong>
                <span class="client-id-small">{{ $client->client_id ?: 'N/A' }}</span>
            </a>
        </div>
        
        <div class="note-description">
            @icon('sticky-note', ['class' => 'icon-sm'])
            {{ Str::limit(strip_tags($note->description), 80) }}
        </div>
        
        <div class="note-assigned-info">
            @icon('user-round', ['class' => 'icon-sm'])
            <span>Assigned to: <strong>{{ $assignedName }}</strong></span>
        </div>
    </div>
    
    <div class="note-item-footer">
        <span class="time-indicator {{ $daysLeftClass }}">
            @icon('hourglass', ['class' => 'icon-sm'])
            {{ $daysLeftText }}
        </span>
        <div class="note-actions">
            <button class="btn-note-action btn-complete" 
                    onclick="closeNotesDeadlineAction({{ $note->id }}, {{ json_encode($note->unique_group_id) }})"
                    title="Mark as Complete">
                @icon('check', ['class' => 'icon-sm']) Complete
            </button>
            <button class="btn-note-action btn-extend btn-extend_note_deadline" 
                    data-noteid="{{ $note->id }}" 
                    data-uniquegroupid="{{ $note->unique_group_id }}" 
                    data-assignnote="{{ e($note->description ?? '') }}" 
                    data-deadlinedate="{{ $note->note_deadline }}"
                    title="Extend Deadline">
                @icon('calendar-plus', ['class' => 'icon-sm']) Extend
            </button>
        </div>
    </div>
</li>

<style>
.note-item-enhanced {
    padding: 15px;
    margin-bottom: 12px;
    border-radius: 8px;
    border-left: 4px solid;
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.note-item-enhanced:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.urgency-overdue,
.urgency-today,
.urgency-urgent {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.urgency-soon {
    border-left-color: #ffc107;
    background: #fffbf0;
}

.urgency-upcoming {
    border-left-color: #17a2b8;
    background: #f0f8ff;
}

.note-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.urgency-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.7em;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-overdue,
.badge-today,
.badge-urgent {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
}

.badge-soon {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    color: #333;
}

.badge-upcoming {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
}

.deadline-date {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9em;
    font-weight: 600;
}

.note-item-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 12px;
}

.note-client-info {
    font-size: 0.95em;
}

.client-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: #005792;
}

.client-link:hover {
    color: #003d66;
    text-decoration: underline;
}

.client-link i,
.client-link svg.lucide {
    color: #667eea;
}

.client-id-small {
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    color: #666;
    font-family: 'Courier New', monospace;
}

.note-description {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.9em;
    color: #555;
    line-height: 1.5;
}

.note-description i,
.note-description svg.lucide {
    color: #ffc107;
    margin-top: 2px;
}

.note-assigned-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85em;
    color: #666;
}

.note-assigned-info i,
.note-assigned-info svg.lucide {
    color: #6f42c1;
}

.note-item-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #f0f0f0;
    flex-wrap: wrap;
    gap: 10px;
}

.time-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85em;
    font-weight: 600;
}

.note-actions {
    display: flex;
    gap: 8px;
}

.btn-note-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid #e0e0e0;
    background: white;
    color: #005792;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8em;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-note-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.btn-complete:hover {
    background: #28a745;
    border-color: #28a745;
    color: white;
}

.btn-extend:hover {
    background: #ffc107;
    border-color: #ffc107;
    color: #333;
}

@media (max-width: 768px) {
    .note-item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .note-item-footer {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .note-actions {
        width: 100%;
    }
    
    .btn-note-action {
        flex: 1;
        justify-content: center;
    }
}
</style>
