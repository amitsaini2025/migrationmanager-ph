{{-- Runtime URLs for Vite layout scripts (Phase 2c) --}}
<script>
window.__CRM_BROADCASTS_JS_URL__ = @json(
    asset('js/broadcasts.js') . '?v=' . (file_exists(public_path('js/broadcasts.js')) ? filemtime(public_path('js/broadcasts.js')) : 1)
);
</script>
