@php
    $showNote = $showNote ?? false;
    $placeholder = $placeholder ?? '';
    $options = $options ?? [];
@endphp
<div class="field" data-key="{{ $key }}">
    <div class="field-top">
        <div>
            <div class="label">{{ $label }}</div>
            <div class="value current-value">{{ $value }}</div>
        </div>
        <div class="actions">
            <button type="button" class="btn-confirm" onclick="confirmField(this)">✓ Confirm</button>
            <button type="button" class="btn-change" onclick="openChange(this)">Request Change</button>
        </div>
    </div>
    <div class="edit-panel">
        <div class="edit-grid">
            @if(($input ?? 'text') === 'select')
                <select class="new-value">
                    <option value="">Select</option>
                    @foreach($options as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            @elseif(($input ?? 'text') === 'textarea')
                <textarea class="new-value" placeholder="{{ $placeholder }}"></textarea>
            @else
                <input type="{{ $input }}" class="new-value" placeholder="{{ $placeholder }}" />
            @endif
            @if($showNote)
                <textarea class="change-note" placeholder="Optional note for our team"></textarea>
            @endif
        </div>
        <div class="edit-actions">
            <button type="button" class="btn-primary" onclick="saveChange(this)">Save Change Request</button>
            <button type="button" class="btn-secondary" onclick="cancelChange(this)">Cancel</button>
        </div>
    </div>
    <div class="status"></div>
</div>
