{{-- Client-detail extra modals (notes, add/edit, management, tab-owned). Loaded on first open / prefetch. --}}
{{-- addclientmodal already includes client-management; do not include it a second time (duplicate IDs). --}}
@include('crm.clients.addclientmodal')
@include('crm.clients.editclientmodal')
@include('crm.clients.modals.edit-matter-office')
