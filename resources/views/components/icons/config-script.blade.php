{{-- Icon map for lucide-init.js (crmIconLegacy) — keep in sync with config/icons.php --}}
<script>
window.crmIconsConfig = @json([
    'defaults' => config('icons.defaults'),
    'legacy' => config('icons.legacy'),
    'spinners' => config('icons.spinners'),
    'brands' => config('icons.brands'),
]);
</script>
{{-- Hidden brand SVG templates for crmIconBrand() in JS --}}
<div id="crm-icon-brand-google" hidden aria-hidden="true">@include('components.icons.brand-google')</div>
