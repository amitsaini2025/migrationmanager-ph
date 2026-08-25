           <!-- Emails Tab -->
           @php
                $emailsTabFragmentUrl = ! empty($encodeId)
                    ? route('clients.detail.emails-tab', array_filter([
                        'client_id' => $encodeId,
                        'client_unique_matter_ref_no' => $id1 ?? null,
                    ], static function ($value) {
                        return $value !== null && $value !== '';
                    }))
                    : '';
           @endphp
           <div class="tab-pane" id="emails-tab"
                @if($emailsTabFragmentUrl !== '') data-emails-url="{{ $emailsTabFragmentUrl }}" @endif>
                @include('crm.emails')
            </div>
