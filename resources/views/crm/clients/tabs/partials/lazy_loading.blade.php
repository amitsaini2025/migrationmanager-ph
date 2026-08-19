{{-- Shared lazy-tab placeholder: spinner image + status text. --}}
<div class="client-detail-lazy-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; min-height: 160px; padding: 24px 0; text-align: center;">
    <img src="{{ URL::asset('img/spinner.svg') }}" alt="" width="40" height="40" class="client-detail-lazy-loading__image" aria-hidden="true">
    <span class="client-detail-lazy-loading__text">{{ $message }}</span>
</div>
