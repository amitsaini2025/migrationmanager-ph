           <!-- Emails Tab -->
           <div class="tab-pane" id="emails-tab"
                @if(!empty($encodeId))
                    data-emails-url="{{ route('clients.detail.emails-tab', array_filter([
                        'client_id' => $encodeId,
                        'client_unique_matter_ref_no' => $id1 ?? null,
                    ], static fn ($v) => $v !== null && $v !== '')) }}"
                @endif>
                @include('crm.emails')
            </div>
