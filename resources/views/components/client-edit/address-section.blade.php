{{-- Address Information Section Component --}}
@props(['clientAddresses', 'searchRoute', 'detailsRoute', 'csrfToken'])

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
                    @php
                        // Current address = latest start_date (first row in display order)
                        $isCurrentAddress = ($index === 0);
                    @endphp
                    <div class="address-entry-compact">
                        <div class="address-compact-grid">
                            <div class="summary-item-inline">
                                <span class="summary-label">ADDRESS:</span>
                                <span class="summary-value">
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
                                <span class="summary-label">START DATE:</span>
                                <span class="summary-value">{{ date('d/m/Y', strtotime($address->start_date)) }}</span>
                            </div>
                            @endif
                            @if($address->end_date)
                            <div class="summary-item-inline">
                                <span class="summary-label">END DATE:</span>
                                <span class="summary-value">{{ date('d/m/Y', strtotime($address->end_date)) }}</span>
                            </div>
                            @endif
                            @if($address->regional_code)
                            <div class="summary-item-inline">
                                <span class="summary-label">REGIONAL CODE:</span>
                                <span class="summary-value strong">{{ $address->regional_code }}</span>
                            </div>
                            @endif
                            @if($isCurrentAddress)
                            <div class="summary-item-inline address-current-summary">
                                <span class="summary-label">CURRENT:</span>
                                <label class="switch switch-readonly" style="margin: 0;" title="Current address">
                                    <input type="checkbox" checked disabled>
                                    <span class="slider round"></span>
                                </label>
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
         class="edit-view hidden" 
         data-search-route="{{ $searchRoute }}"
         data-details-route="{{ $detailsRoute }}"
         data-csrf-token="{{ $csrfToken }}"
         data-address-count="{{ count($clientAddresses) }}">
        
        <div id="addresses-container">
            @if(count($clientAddresses) > 0)
                @foreach($clientAddresses as $index => $address)
                    <x-client-edit.address-field 
                        :index="$index" 
                        :address="$address" 
                        :showRemoveButton="$index > 0"
                        :isCurrent="$index === 0"
                    />
                @endforeach
            @else
                {{-- Default empty address entry --}}
                <x-client-edit.address-field 
                    :index="0" 
                    :address="null" 
                    :showRemoveButton="false"
                    :isCurrent="true"
                />
            @endif
        </div>
        
        <button type="button" class="add-another-address" onclick="addAnotherAddress()">
            @icon('fa-plus') Add Another Address
        </button>

        <div class="edit-actions">
            <button type="button" class="btn btn-primary" onclick="saveAddressInfo()">Save</button>
            <button type="button" class="btn btn-secondary" onclick="cancelEdit('addressInfo')">Cancel</button>
        </div>
    </div>
</section>

