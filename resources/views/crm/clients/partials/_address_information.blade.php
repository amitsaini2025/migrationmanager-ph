{{-- Address Information Section --}}
<section class="form-section">
    <div class="section-header">
        <h3>@icon('fa-home') Address Information</h3>
        <div class="section-actions">
            <button type="button" class="edit-section-btn" onclick="toggleEditMode('addressInfo')">
                @icon('fa-pen')
            </button>
            <button type="button" class="add-section-btn" onclick="addAddress()" title="Add Address">
                @icon('fa-plus')
            </button>
        </div>
    </div>
    
    {{-- Summary View --}}
    <div id="addressInfoSummary" class="summary-view">
        @if($clientAddresses->count() > 0)
            <div>
                @foreach($clientAddresses as $index => $address)
                    <div class="address-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: center;">
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ADDRESS:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">
                                    @php
                                        $addressParts = array_filter([
                                            $address->address_line_1,
                                            $address->address_line_2,
                                            $address->suburb,
                                            $address->state,
                                            $address->zip,
                                            ($address->country && $address->country !== 'Australia') ? $address->country : null
                                        ]);
                                        
                                        if (!empty($addressParts)) {
                                            echo implode(', ', $addressParts);
                                        } elseif ($address->address) {
                                            echo $address->address;
                                        } else {
                                            echo 'Not set';
                                        }
                                    @endphp
                                </span>
                            </div>
                            @if($address->start_date)
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">START DATE:</span>
                                <span class="summary-value" style="color: #212529;">{{ date('d/m/Y', strtotime($address->start_date)) }}</span>
                            </div>
                            @endif
                            @if($address->end_date)
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">END DATE:</span>
                                <span class="summary-value" style="color: #212529;">{{ date('d/m/Y', strtotime($address->end_date)) }}</span>
                            </div>
                            @endif
                            @if($address->regional_code)
                            <div class="summary-item-inline">
                                <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">REGIONAL CODE:</span>
                                <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $address->regional_code }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <p>No addresses added yet.</p>
            </div>
        @endif
    </div>

    {{-- Edit View --}}
    <div id="addressInfoEdit" 
         class="edit-view" 
         style="display: none;"
         data-search-route="{{ route('clients.searchAddressFull') }}"
         data-details-route="{{ route('clients.getPlaceDetails') }}"
         data-csrf-token="{{ csrf_token() }}"
         data-address-count="{{ count($clientAddresses) }}">
        
        <div id="addresses-container">
            @if(count($clientAddresses) > 0)
                @foreach($clientAddresses as $index => $address)
                @include('crm.clients.partials._address_entry', [
                    'index' => $index,
                    'address' => $address,
                    'showRemoveButton' => $index > 0
                ])
                @endforeach
            @else
                {{-- Default empty address entry --}}
                @include('crm.clients.partials._address_entry', [
                    'index' => 0,
                    'address' => null,
                    'showRemoveButton' => false
                ])
            @endif
        </div>
        
        <button type="button" class="add-another-address" onclick="addAnotherAddress()">
            @icon('fa-plus') Add Another Address
        </button>

        <div class="edit-actions">
            <button type="button" class="btn btn-primary" onclick="console.log('Save button clicked!'); saveAddressInfo();">Save</button>
            <button type="button" class="btn btn-secondary" onclick="cancelEdit('addressInfo')">Cancel</button>
        </div>
    </div>
</section>

