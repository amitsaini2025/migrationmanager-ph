{{-- Lightweight shells with stable IDs so existing $('#emailmodal').modal('show') and click handlers still resolve. --}}
@foreach(\App\Support\ClientDetailModals::stubs() as $stub)
<div @if(!empty($stub['id'])) id="{{ $stub['id'] }}" @endif
     class="{{ $stub['class'] }}"
     data-lazy-modal="1"
     data-lazy-pack="{{ $stub['pack'] }}"
     @if(!empty($stub['matchClass'])) data-lazy-class="{{ $stub['matchClass'] }}" @endif
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body p-4 text-muted">Loading&hellip;</div>
        </div>
    </div>
</div>
@endforeach
