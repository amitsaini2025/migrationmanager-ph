@props([
    'title',
    'count',
    'icon' => 'chart-column',
    'iconClass' => 'icon-active',
    'route' => null,
    'color' => 'primary'
])

@php
    // Define gradient colors based on icon class
    $gradients = [
        'icon-active' => ['from' => '#4e73df', 'to' => '#224abe', 'icon-bg' => 'rgba(78, 115, 223, 0.1)'],
        'icon-pending' => ['from' => '#f6c23e', 'to' => '#e0a800', 'icon-bg' => 'rgba(246, 194, 62, 0.1)'],
        'icon-success' => ['from' => '#1cc88a', 'to' => '#13855c', 'icon-bg' => 'rgba(28, 200, 138, 0.1)'],
    ];
    
    $gradient = $gradients[$iconClass] ?? $gradients['icon-active'];
@endphp

<div class="kpi-card-modern">
    <div class="kpi-card-inner">
        <div class="kpi-icon-wrapper" style="background: {{ $gradient['icon-bg'] }};">
            {!! \App\Helpers\IconHelper::render($icon, ['style' => 'color: ' . $gradient['from'] . ';']) !!}
        </div>
        <div class="kpi-content">
            <h3 class="kpi-title">{{ $title }}</h3>
            <div class="kpi-count">
                @if($route)
                    <a href="{{ $route }}" class="kpi-count-link">{{ number_format($count) }}</a>
                @else
                    <span class="kpi-count-number">{{ number_format($count) }}</span>
                @endif
            </div>
        </div>
    </div>
    <div class="kpi-card-shine"></div>
</div>

<style>
.kpi-card-modern {
    position: relative;
    background: white;
    border-radius: 12px;
    padding: 20px 18px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
    cursor: pointer;
}

.kpi-card-modern:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    border-color: rgba(0, 0, 0, 0.1);
}

.kpi-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--from-color, #4e73df), var(--to-color, #224abe));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.kpi-card-modern:hover::before {
    opacity: 1;
}

.kpi-card-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
    z-index: 1;
}

.kpi-icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.kpi-card-modern:hover .kpi-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

.kpi-icon-wrapper svg.lucide,
.kpi-icon-wrapper i {
    width: 1.8em;
    height: 1.8em;
    transition: all 0.3s ease;
}

.kpi-content {
    width: 100%;
}

.kpi-title {
    margin: 0 0 10px 0;
    font-size: 0.8em;
    color: #858796;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.kpi-count {
    margin: 0;
}

.kpi-count-number,
.kpi-count-link {
    font-size: 2.2em;
    font-weight: 800;
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
    display: inline-block;
    transition: all 0.3s ease;
    text-decoration: none;
}

.kpi-count-link:hover {
    transform: scale(1.05);
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Shine effect on hover */
.kpi-card-shine {
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent 30%,
        rgba(255, 255, 255, 0.3) 50%,
        transparent 70%
    );
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

.kpi-card-modern:hover .kpi-card-shine {
    transform: translateX(100%);
}

/* Specific icon styling */
.icon-active {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
}

.icon-pending {
    background: linear-gradient(135deg, #f6c23e 0%, #e0a800 100%);
}

.icon-success {
    background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
}

@media (max-width: 768px) {
    .kpi-card-modern {
        padding: 18px 15px;
    }
    
    .kpi-icon-wrapper {
        width: 50px;
        height: 50px;
    }
    
    .kpi-icon-wrapper svg.lucide,
    .kpi-icon-wrapper i {
        width: 1.5em;
        height: 1.5em;
    }
    
    .kpi-count-number,
    .kpi-count-link {
        font-size: 1.8em;
    }
}
</style>
