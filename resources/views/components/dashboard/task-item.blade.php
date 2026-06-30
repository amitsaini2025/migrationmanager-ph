@props(['note'])

@php
    $client = $note->client;
    
    // Handle tasks with and without deadlines
    if ($note->note_deadline) {
        $deadline = new DateTime($note->note_deadline);
        $today = new DateTime();
        $isOverdue = $deadline < $today;
        
        if ($isOverdue) {
            $interval = $today->diff($deadline);
            $daysOverdue = $interval->days;
            $daysLeftText = $daysOverdue . ' day' . ($daysOverdue != 1 ? 's' : '') . ' overdue';
            $urgencyClass = 'overdue';
        } else {
            $interval = $today->diff($deadline);
            $daysLeft = $interval->days;
            
            if ($daysLeft == 0) {
                $daysLeftText = 'Today';
                $urgencyClass = 'today';
            } elseif ($daysLeft == 1) {
                $daysLeftText = 'Tomorrow';
                $urgencyClass = 'tomorrow';
            } elseif ($daysLeft <= 7) {
                $daysLeftText = $deadline->format('D');
                $urgencyClass = 'this-week';
            } else {
                $daysLeftText = $deadline->format('M d');
                $urgencyClass = 'upcoming';
            }
        }
        $deadlineFormatted = \Carbon\Carbon::parse($note->note_deadline)->format('Y-m-d');
    } else {
        // Task without deadline
        $daysLeftText = 'No deadline';
        $urgencyClass = 'no-deadline';
        $isOverdue = false;
        $deadlineFormatted = '';
    }
    
    $assignedUser = $note->assignedUser ?? null;
    $assignedName = $assignedUser ? $assignedUser->first_name . ' ' . $assignedUser->last_name : 'Unassigned';

    // Client display: Personal Actions have null client; companies use company name from accessor
    $clientName = $client ? trim($client->company_name_or_personal_name) : 'Personal Action';
    if ($client && $clientName === '') {
        $clientName = trim($client->first_name . ' ' . $client->last_name) ?: 'Client';
    }
    $clientCode = $client && $client->client_id ? $client->client_id : '';
    $clientId = $client ? (string) $client->id : '';

    // Safely encode data for attributes
    $descriptionEncoded = htmlspecialchars($note->description, ENT_QUOTES, 'UTF-8');
    $uniqueGroupIdSafe = $note->unique_group_id ? json_encode($note->unique_group_id) : 'null';
    $clientDetailUrl = $client ? route('clients.detail', base64_encode(convert_uuencode($client->id))) : '';
@endphp

<li class="todo-task-item"
    data-task-id="{{ $note->id }}"
    data-unique-group-id="{{ $note->unique_group_id ?? '' }}"
    data-client-id="{{ $clientId }}"
    data-client-detail-url="{{ $clientDetailUrl }}"
    data-client-name="{{ e($clientName) }}"
    data-client-code="{{ e($clientCode) }}"
    data-description="{{ $descriptionEncoded }}"
    data-deadline="{{ $note->note_deadline ?? '' }}"
    data-deadline-formatted="{{ $deadlineFormatted }}"
    data-assigned-to="{{ e($assignedName) }}"
    data-urgency="{{ $urgencyClass }}">
    
    <div class="todo-task-checkbox">
        <input type="checkbox"
               id="task-{{ $note->id }}"
               class="task-complete-checkbox"
               onclick="event.stopPropagation(); handleTaskComplete({{ $note->id }}, {{ $uniqueGroupIdSafe }})">
        <label for="task-{{ $note->id }}"></label>
    </div>
    
    <div class="todo-task-content" onclick="openTaskDetail({{ $note->id }})">
        <div class="todo-task-title">
            {{ Str::limit(strip_tags($note->description), 60) }}
        </div>
        <div class="todo-task-meta">
            <span class="task-client-info">
                @icon('user', ['class' => 'icon-sm'])
                {{ $clientName }}
                @if($clientCode)
                    <span class="task-client-code">({{ $clientCode }})</span>
                @endif
            </span>
        </div>
    </div>
    
    <div class="todo-task-actions">
        <span class="todo-task-due {{ $urgencyClass }}">
            @if($note->note_deadline)
                @if($isOverdue)
                    @icon('circle-alert', ['class' => 'icon-sm'])
                @else
                    @icon('calendar', ['class' => 'icon-sm'])
                @endif
            @else
                @icon('infinity', ['class' => 'icon-sm'])
            @endif
            {{ $daysLeftText }}
        </span>
        <div class="todo-task-hover-actions">
            @if($note->note_deadline)
                <button class="todo-action-btn"
                        onclick="event.stopPropagation(); openExtendModal({{ $note->id }})"
                        title="Extend Deadline">
                    @icon('calendar-plus', ['class' => 'icon-sm'])
                </button>
            @else
                <button class="todo-action-btn"
                        onclick="event.stopPropagation(); openAddDeadlineModal({{ $note->id }})"
                        title="Add Deadline">
                    @icon('calendar-plus', ['class' => 'icon-sm'])
                </button>
            @endif
        </div>
    </div>
</li>

<style>
.todo-task-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: white;
    border-radius: 8px;
    margin-bottom: 2px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    gap: 12px;
}

.todo-task-item:hover {
    background: #f5f5f5;
    border-color: #e0e0e0;
}

.todo-task-item:hover .todo-task-hover-actions {
    opacity: 1;
    visibility: visible;
}

.todo-task-checkbox {
    position: relative;
    flex-shrink: 0;
}

.task-complete-checkbox {
    appearance: none;
    width: 20px;
    height: 20px;
    border: 2px solid #c0c0c0;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.task-complete-checkbox:hover {
    border-color: #2564cf;
}

.task-complete-checkbox:checked {
    background: #2564cf;
    border-color: #2564cf;
}

.task-complete-checkbox:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 14px;
    font-weight: bold;
}

.todo-task-content {
    flex: 1;
    min-width: 0;
    cursor: pointer;
}

.todo-task-title {
    font-size: 14px;
    color: #333;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.todo-task-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #666;
}

.task-client-info {
    display: flex;
    align-items: center;
    gap: 6px;
}

.task-client-info i,
.task-client-info svg.lucide {
    color: #999;
    font-size: 11px;
}

.task-client-code {
    color: #999;
    font-size: 11px;
}

.todo-task-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.todo-task-due {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 4px;
    white-space: nowrap;
}

.todo-task-due i,
.todo-task-due svg.lucide {
    font-size: 11px;
}

.todo-task-due.overdue {
    color: #d32f2f;
    background: #ffebee;
}

.todo-task-due.today {
    color: #f57c00;
    background: #fff3e0;
}

.todo-task-due.tomorrow {
    color: #f57c00;
    background: #fff3e0;
}

.todo-task-due.this-week {
    color: #1976d2;
    background: #e3f2fd;
}

.todo-task-due.upcoming {
    color: #666;
    background: #f5f5f5;
}

.todo-task-due.no-deadline {
    color: #999;
    background: #f9f9f9;
    font-style: italic;
}

.todo-task-hover-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
}

.todo-action-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #666;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.todo-action-btn:hover {
    background: #e0e0e0;
    color: #333;
}

@media (max-width: 768px) {
    .todo-task-item {
        padding: 10px 12px;
    }
    
    .todo-task-title {
        font-size: 13px;
    }
    
    .todo-task-meta {
        font-size: 11px;
    }
    
    .todo-task-hover-actions {
        opacity: 1;
        visibility: visible;
    }
}
</style>
