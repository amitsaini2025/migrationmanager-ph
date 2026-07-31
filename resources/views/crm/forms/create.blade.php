<script type="text/javascript">
        var gk_isXlsx = false;
        var gk_xlsxFileLookup = {};
        var gk_fileData = {};
        function filledCell(cell) {
          return cell !== '' && cell != null;
        }
        function loadFileData(filename) {
        if (gk_isXlsx && gk_xlsxFileLookup[filename]) {
            try {
                var workbook = XLSX.read(gk_fileData[filename], { type: 'base64' });
                var firstSheetName = workbook.SheetNames[0];
                var worksheet = workbook.Sheets[firstSheetName];

                // Convert sheet to JSON to filter blank rows
                var jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, blankrows: false, defval: '' });
                // Filter out blank rows (rows where all cells are empty, null, or undefined)
                var filteredData = jsonData.filter(row => row.some(filledCell));

                // Heuristic to find the header row by ignoring rows with fewer filled cells than the next row
                var headerRowIndex = filteredData.findIndex((row, index) =>
                  row.filter(filledCell).length >= filteredData[index + 1]?.filter(filledCell).length
                );
                // Fallback
                if (headerRowIndex === -1 || headerRowIndex > 25) {
                  headerRowIndex = 0;
                }

                // Convert filtered JSON back to CSV
                var csv = XLSX.utils.aoa_to_sheet(filteredData.slice(headerRowIndex)); // Create a new sheet from filtered array of arrays
                csv = XLSX.utils.sheet_to_csv(csv, { header: 1 });
                return csv;
            } catch (e) {
                console.error(e);
                return "";
            }
        }
        return gk_fileData[filename] || "";
        }
        </script>@extends('layouts.app')

@section('title', 'Create Form 956')

@section('content')
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h1 class="text-2xl font-bold text-gray-900">Create Form 956</h1>
    </div>
    <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
        <form action="{{ route('forms.store') }}" method="POST" class="space-y-6">
            @csrf
            <!-- Client Selection -->
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700">Select Client</label>
                <select name="client_id" id="client_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('client_id') border-red-300 @enderror">
                    <option value="">Select a client</option>
                    @foreach ($clients as $optionClient)
                        <option value="{{ $optionClient->id }}" {{ (string) $optionClient->id === (string) old('client_id', $client->id ?? '') ? 'selected' : '' }}>{{ trim(($optionClient->first_name ?? '') . ' ' . ($optionClient->last_name ?? '')) }}</option>
                    @endforeach
                </select>
                @error('client_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Agent Details (Read-only) -->
            <div>
                <h3 class="text-lg font-medium text-gray-900">Agent Details</h3>
                <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Agent Name</label>
                        <input type="text" value="{{ trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')) ?: ($agent->agent_name ?? '') }}" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Business Name</label>
                        <input type="text" value="{{ $agent->business_name ?? '' }}" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100">
                    </div>
                </div>
            </div>
            <input type="hidden" name="agent_id" value="{{ $agent->id }}">

            <!-- Application Details -->
            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Application Details</h3>
                
                <!-- Application Type -->
                <div>
                    <label for="application_type" class="block text-sm font-medium text-gray-700">Type of Application</label>
                    <select name="application_type" id="application_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="visa">Visa Application</option>
                        <option value="citizenship">Citizenship</option>
                        <option value="sponsorship">Sponsorship</option>
                        <option value="nomination">Nomination</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Date Lodged -->
                <div>
                    <label for="date_lodged" class="block text-sm font-medium text-gray-700">Date Lodged</label>
                    <input type="date" name="date_lodged" id="date_lodged" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <!-- Not Lodged Checkbox -->
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="not_lodged" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Application not yet lodged</span>
                    </label>
                </div>
            </div>

            <!-- Form Type (Hidden - Always Appointment) -->
            <input type="hidden" name="form_type" value="appointment">

            <!-- Part A: New Appointment -->
            <div id="part-a" class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Part A: New Appointment</h3>
                
                <!-- Agent Type (Pre-selected as Registered Migration Agent) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Agent Type</label>
                    <div class="mt-2 space-y-2">
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_registered_migration_agent" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Registered Migration Agent</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_legal_practitioner" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Legal Practitioner</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_exempt_person" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Exempt Person</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Type of Assistance -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Type of Assistance</label>
                    <div class="mt-2 space-y-2">
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_visa_application" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Visa Application</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_sponsorship" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Sponsorship</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_nomination" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Nomination</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_cancellation" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Cancellation</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_other" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Other</span>
                            </label>
                            <input type="text" name="assistance_other_details" value="{{ old('assistance_other_details') }}" placeholder="Specify other assistance" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                    </div>
                </div>

                <!-- Question 5 (As Above) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Question 5 - Business Address</label>
                    <input type="text" name="business_address" value="As Above" readonly class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100">
                </div>

                <!-- Question 7 (Yes) -->
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="question_7" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Question 7 - Registered Migration Agent</span>
                    </label>
                </div>

                <!-- Question 10 (No) -->
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="question_10" value="0" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Question 10 - Exempt Person</span>
                    </label>
                </div>

                <!-- Question 17 (Always Yes) -->
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_authorized_recipient" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Authorized Recipient (Question 17)</span>
                    </label>
                </div>
            </div>

            <!-- Part C: Declarations -->
            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Part C: Declarations</h3>
                
                <!-- Question 23 (Appointment of Registered Migration Agent) -->
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="agent_declared" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Appointment of Registered Migration Agent (Question 23)</span>
                    </label>
                    <input type="date" name="agent_declaration_date" value="{{ date('Y-m-d') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <!-- Client Declaration -->
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="client_declared" value="1" checked class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Client Declaration</span>
                    </label>
                    <input type="date" name="client_declaration_date" value="{{ date('Y-m-d') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
            </div>

            <div>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">Create Form</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Handle not lodged checkbox
    document.querySelector('input[name="not_lodged"]').addEventListener('change', function() {
        const dateLodgedInput = document.getElementById('date_lodged');
        dateLodgedInput.disabled = this.checked;
        if (this.checked) {
            dateLodgedInput.value = '';
        }
    });
</script>
@endsection