{{-- Edit Matter Office Modal --}}
<div class="modal fade" id="editMatterOfficeModal" tabindex="-1" role="dialog" aria-labelledby="editMatterOfficeLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editMatterOfficeLabel">
                    @icon('fa-building') Assign Office to Matter
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editMatterOfficeForm" method="POST" action="{{ route('matters.update-office') }}">
                @csrf
                <input type="hidden" name="matter_id" id="edit_matter_id">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        @icon('fa-info-circle')
                        <strong>Matter Details:</strong>
                        <div id="matter_details" class="mt-2"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_office_id">
                            Handling Office <span class="text-danger">*</span>
                        </label>
                        <select class="form-control form-control-lg" 
                                name="office_id" 
                                id="edit_office_id" 
                                required>
                            <option value="">-- Select Office --</option>
                            @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                <option value="{{$office->id}}">
                                    {{$office->office_name}}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            This will affect all financial reports and statistics for this matter
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="office_notes">Notes (Optional)</label>
                        <textarea class="form-control" 
                                  name="notes" 
                                  id="office_notes" 
                                  rows="3"
                                  placeholder="Add any notes about this office assignment..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        @icon('fa-times') Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        @icon('fa-save') Save Office Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
