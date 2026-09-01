@props([
    'title',
    'metric',
    'data' => [],
    'icon' => 'fa-tasks',
    'iconClass' => 'icon-active',
    'route' => null,
    'extraLines' => [],
])

@php
    $gradients = [
        'icon-active' => ['from' => '#4e73df', 'to' => '#224abe', 'icon-bg' => 'rgba(78, 115, 223, 0.1)'],
        'icon-pending' => ['from' => '#f6c23e', 'to' => '#e0a800', 'icon-bg' => 'rgba(246, 194, 62, 0.1)'],
        'icon-success' => ['from' => '#1cc88a', 'to' => '#13855c', 'icon-bg' => 'rgba(28, 200, 138, 0.1)'],
        'icon-call' => ['from' => '#36b9cc', 'to' => '#258391', 'icon-bg' => 'rgba(54, 185, 204, 0.12)'],
    ];
    $gradient = $gradients[$iconClass] ?? $gradients['icon-active'];
    $total = (int) ($data['total'] ?? 0);
    $clients = (int) ($data['clients'] ?? 0);
    $leads = (int) ($data['leads'] ?? 0);
    $personal = (int) ($data['personal'] ?? 0);
    $newCount = (int) ($data['new'] ?? 0);
    $returning = (int) ($data['returning'] ?? 0);
    $current = (int) ($data['current'] ?? 0);
@endphp

<div class="workload-card" data-workload-metric="{{ $metric }}" role="button" tabindex="0" title="Click for details">
    <div class="workload-card-inner">
        <div class="workload-icon-wrapper" style="background: {{ $gradient['icon-bg'] }};">
            {!! \App\Helpers\IconHelper::render($icon, ['style' => 'color: ' . $gradient['from'] . ';']) !!}
        </div>
        <div class="workload-content">
            <h3 class="workload-title">{{ $title }}</h3>
            <div class="workload-count">{{ number_format($total) }}</div>
            <p class="workload-split">
                {{ $clients }} clients · {{ $leads }} leads@if($personal > 0) · {{ $personal }} personal@endif
            </p>
            @if($newCount > 0 || $returning > 0 || $current > 0)
                <p class="workload-class-split">
                    @if($newCount > 0)<span class="workload-badge workload-badge-new">{{ $newCount }} new</span>@endif
                    @if($returning > 0)<span class="workload-badge workload-badge-returning">{{ $returning }} returning</span>@endif
                    @if($current > 0)<span class="workload-badge workload-badge-current">{{ $current }} current</span>@endif
                </p>
            @endif
            @foreach($extraLines as $line)
                <p class="workload-extra">{{ $line }}</p>
            @endforeach
            @if(!empty($data['breakdown']))
                <p class="workload-extra">
                    @foreach($data['breakdown'] as $group => $count)
                        {{ $group }} {{ $count }}@if(!$loop->last) · @endif
                    @endforeach
                </p>
            @endif
            @if($route)
                <a href="{{ $route }}" class="workload-view-all" onclick="event.stopPropagation()">View all →</a>
            @endif
        </div>
    </div>
</div>
