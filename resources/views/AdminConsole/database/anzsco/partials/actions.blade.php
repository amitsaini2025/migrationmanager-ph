<div class="btn-group btn-group-sm">
    <a href="{{ route('adminconsole.database.anzsco.edit', $occupation->id) }}" 
       class="btn btn-info" title="Edit">
        @icon('fa-edit')
    </a>
    <button type="button" class="btn btn-danger delete-occupation" 
            data-id="{{ $occupation->id }}" 
            data-title="{{ $occupation->occupation_title }}"
            title="Delete">
        @icon('fa-trash')
    </button>
</div>

