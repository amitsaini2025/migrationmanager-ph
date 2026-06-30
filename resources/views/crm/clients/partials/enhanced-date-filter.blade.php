<!-- Enhanced Date Filter Section -->
<div class="date-filter-section">
    <h5>@icon('calendar', ['class' => 'icon-sm']) Date Filter</h5>
    
    <!-- Hidden field to track filter type -->
    <input type="hidden" name="date_filter_type" id="date_filter_type" value="{{ request('date_filter_type', '') }}">
    
    <!-- Quick Filter Chips -->
    <div class="quick-filters">
        <span class="quick-filter-chip {{ request('date_filter_type') == 'today' ? 'active' : '' }}" data-filter="today">
            @icon('calendar-days', ['class' => 'icon-sm']) Today
        </span>
        <span class="quick-filter-chip {{ request('date_filter_type') == 'this_week' ? 'active' : '' }}" data-filter="this_week">
            @icon('calendar-range', ['class' => 'icon-sm']) This Week
        </span>
        <span class="quick-filter-chip {{ request('date_filter_type') == 'this_month' ? 'active' : '' }}" data-filter="this_month">
            @icon('calendar', ['class' => 'icon-sm']) This Month
        </span>
        <span class="quick-filter-chip {{ request('date_filter_type') == 'this_quarter' ? 'active' : '' }}" data-filter="this_quarter">
            @icon('calendar-check', ['class' => 'icon-sm']) This Quarter
        </span>
        <span class="quick-filter-chip {{ request('date_filter_type') == 'this_year' ? 'active' : '' }}" data-filter="this_year">
            @icon('calendar', ['class' => 'icon-sm']) This Year
        </span>
        <span class="quick-filter-chip {{ request('date_filter_type') == 'last_month' ? 'active' : '' }}" data-filter="last_month">
            @icon('calendar-minus', ['class' => 'icon-sm']) Last Month
        </span>
        <span class="quick-filter-chip {{ request('date_filter_type') == 'last_quarter' ? 'active' : '' }}" data-filter="last_quarter">
            @icon('calendar-minus', ['class' => 'icon-sm']) Last Quarter
        </span>
        <span class="quick-filter-chip {{ request('date_filter_type') == 'last_year' ? 'active' : '' }}" data-filter="last_year">
            @icon('calendar-minus', ['class' => 'icon-sm']) Last Year
        </span>
    </div>

    <div class="divider-text">Or Custom Range</div>

    <!-- Custom Date Range -->
    <div class="date-range-wrapper">
        <div class="form-group">
            <label for="from_date" class="col-form-label" style="color:#4a5568 !important;">
                @icon('calendar-plus', ['class' => 'icon-sm']) From Date
            </label>
            <input type="text" name="from_date" id="from_date" value="{{ old('from_date', Request::get('from_date')) }}" class="form-control datepicker" autocomplete="off" placeholder="Select start date">
        </div>
        
        <span class="date-range-arrow">→</span>
        
        <div class="form-group">
            <label for="to_date" class="col-form-label" style="color:#4a5568 !important;">
                @icon('calendar-check', ['class' => 'icon-sm']) To Date
            </label>
            <input type="text" name="to_date" id="to_date" value="{{ old('to_date', Request::get('to_date')) }}" class="form-control datepicker" autocomplete="off" placeholder="Select end date">
        </div>
    </div>

    <div class="divider-text">Or Financial Year</div>

    <!-- Financial Year Selector -->
    <div class="fy-selector">
        <label for="financial_year" class="col-form-label" style="color:#4a5568 !important;">
            @icon('chart-line', ['class' => 'icon-sm']) Financial Year:
        </label>
        <select name="financial_year" id="financial_year" class="form-control">
            <option value="">Select Financial Year</option>
            <?php
            $currentYear = date('Y');
            $currentMonth = date('n');
            // Australian FY starts in July
            $startYear = ($currentMonth >= 7) ? $currentYear : $currentYear - 1;
            
            // Generate last 5 FY and next 2 FY
            for ($i = 2; $i >= -5; $i--) {
                $fyStart = $startYear - $i;
                $fyEnd = $fyStart + 1;
                $fyValue = $fyStart . '-' . $fyEnd;
                $fyLabel = 'FY ' . $fyStart . '-' . substr($fyEnd, -2);
                $selected = request('financial_year') == $fyValue ? 'selected' : '';
                echo "<option value=\"{$fyValue}\" {$selected}>{$fyLabel}</option>";
            }
            ?>
        </select>
    </div>
</div>
