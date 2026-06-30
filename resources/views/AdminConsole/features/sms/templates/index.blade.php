@extends('layouts.crm_client_detail')
@section('title', 'SMS Templates')

@section('content')
<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg">
            </div>
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h4>@icon('fa-file-alt') SMS Templates</h4>
                            <div class="card-header-action">
                                <a href="{{ route('adminconsole.features.sms.templates.create') }}" class="btn btn-primary">
                                    @icon('fa-plus') Create Template
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Message Preview</th>
                                            <th>Status</th>
                                            <th>Usage Count</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($templates as $template)
                                        <tr>
                                            <td>
                                                <strong>{{ $template->title }}</strong>
                                                @if($template->description)
                                                <br><small class="text-muted">{{ $template->description }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($template->category)
                                                <span class="badge badge-info">{{ $template->category }}</span>
                                                @else
                                                <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    {{ $template->message }}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $template->is_active ? 'success' : 'secondary' }}">
                                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary">{{ $template->usage_count ?? 0 }}</span>
                                            </td>
                                            <td>
                                                <small>{{ $template->created_at->format('M d, Y') }}</small>
                                            </td>
                                            <td>
                                                <a href="{{ route('adminconsole.features.sms.templates.edit', $template->id) }}" class="btn btn-sm btn-info">
                                                    @icon('fa-edit') Edit
                                                </a>
                                                <button class="btn btn-sm btn-danger" onclick="deleteTemplate({{ $template->id }})">
                                                    @icon('fa-trash') Delete
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                @icon('fa-file-alt', ['class' => 'fa-2x text-muted mb-2'])
                                                <p class="text-muted">No templates found</p>
                                                <a href="{{ route('adminconsole.features.sms.templates.create') }}" class="btn btn-primary">
                                                    @icon('fa-plus') Create First Template
                                                </a>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            @if($templates->hasPages())
                            <div class="d-flex justify-content-center">
                                {{ $templates->links() }}
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this template?')) {
        $.ajax({
            url: `/adminconsole/features/sms/templates/${templateId}`,
            method: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert('Template deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                if (response && response.message) {
                    alert('Error: ' + response.message);
                } else {
                    alert('An error occurred while deleting the template');
                }
            }
        });
    }
}
</script>
@endsection
