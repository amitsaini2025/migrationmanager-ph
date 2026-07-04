{{-- Icon map for lucide-init.js (crmIconLegacy) — keep in sync with config/icons.php --}}
@php
    $crmIconsConfig = [
        'defaults' => config('icons.defaults'),
        'legacy' => config('icons.legacy'),
        'spinners' => config('icons.spinners'),
        'brands' => config('icons.brands'),
    ];
@endphp
<script>
window.crmIconsConfig = @json($crmIconsConfig);
</script>
{{-- Hidden brand SVG templates for crmIconBrand() in JS --}}
<div id="crm-icon-brand-google" hidden aria-hidden="true">@include('components.icons.brand-google')</div>
