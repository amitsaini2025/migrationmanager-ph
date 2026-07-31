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

@section('title', 'Edit Form 956')

@section('content')
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h1 class="text-2xl font-bold text-gray-900">Edit Form 956</h1>
    </div>
    <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
        <form action="{{ route('forms.update', $form) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <!-- Client Selection -->
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700">Select Client</label>
                <select name="client_id" id="client_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('client_id') border-red-300 @enderror">
                    <option value="">Select a client</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" {{ $client->id == old('client_id', $form->client_id) ? 'selected' : '' }}>{{ $client->full_name }}</option>
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
                        <input type="text" value="{{ $form->agent ? (trim(($form->agent->first_name ?? '') . ' ' . ($form->agent->last_name ?? '')) ?: ($form->agent->agent_name ?? '')) : '' }}" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Business Name</label>
                        <input type="text" value="{{ $form->agent->business_name ?? '' }}" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100">
                    </div>
                </div>
            </div>
            <input type="hidden" name="agent_id" value="{{ $form->agent_id }}">

            <!-- Form Type -->
            <div>
                <label for="form_type" class="block text-sm font-medium text-gray-700">Form Type</label>
                <select name="form_type" id="form_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('form_type') border-red-300 @enderror">
                    <option value="appointment" {{ old('form_type', $form->form_type) == 'appointment' ? 'selected' : '' }}>New Appointment (Part A)</option>
                    <option value="withdrawal" {{ old('form_type', $form->form_type) == 'withdrawal' ? 'selected' : '' }}>Withdrawal (Part B)</option>
                </select>
                @error('form_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Part A: New Appointment -->
            <div id="part-a" class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Part A: New Appointment</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Agent Type</label>
                    <div class="mt-2 space-y-2">
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_registered_migration_agent" value="1" {{ old('is_registered_migration_agent', $form->is_registered_migration_agent) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Registered Migration Agent</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_legal_practitioner" value="1" {{ old('is_legal_practitioner', $form->is_legal_practitioner) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Legal Practitioner</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_exempt_person" value="1" {{ old('is_exempt_person', $form->is_exempt_person) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Exempt Person</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Type of Assistance</label>
                    <div class="mt-2 space-y-2">
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_visa_application" value="1" {{ old('assistance_visa_application', $form->assistance_visa_application) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Visa Application</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_sponsorship" value="1" {{ old('assistance_sponsorship', $form->assistance_sponsorship) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Sponsorship</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_nomination" value="1" {{ old('assistance_nomination', $form->assistance_nomination) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Nomination</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_cancellation" value="1" {{ old('assistance_cancellation', $form->assistance_cancellation) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Cancellation</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_ministerial_intervention" value="1" {{ old('assistance_ministerial_intervention', $form->assistance_ministerial_intervention) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Ministerial Intervention</span>
                            </label>
                        </div>
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="assistance_other" value="1" {{ old('assistance_other', $form->assistance_other) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Other</span>
                            </label>
                            <input type="text" name="assistance_other_details" value="{{ old('assistance_other_details', $form->assistance_other_details) }}" placeholder="Specify other assistance" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('assistance_other_details') border-red-300 @enderror">
                            @error('assistance_other_details')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_authorized_recipient" value="1" {{ old('is_authorized_recipient', $form->is_authorized_recipient) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Authorized Recipient</span>
                    </label>
                </div>
            </div>

            <!-- Part B: Withdrawal -->
            <div id="part-b" class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Part B: Withdrawal</h3>
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="withdraw_authorized_recipient" value="1" {{ old('withdraw_authorized_recipient', $form->withdraw_authorized_recipient) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Withdraw Authorized Recipient Status</span>
                    </label>
                </div>
            </div>

            <!-- Part C: Declarations -->
            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Part C: Declarations</h3>
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="agent_declared" value="1" {{ old('agent_declard', $form->agent_declared) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Agent Declaration</span>
                    </label>
                    <input type="date" name="agent_declaration_date" value="{{ old('agent_declaration_date', $form->agent_declaration_date ? $form->agent_declaration_date->format('Y-m-d') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('agent_declaration_date') border-red-300 @enderror">
                    @error('agent_declaration_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="client_declared" value="1" {{ old('client_declared', $form->client_declared) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Client Declaration</span>
                    </label>
                    <input type="date" name="client_declaration_date" value="{{ old('client_declaration_date', $form->client_declaration_date ? $form->client_declaration_date->format('Y-m-d') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('client_declaration_date') border-red-300 @enderror">
                    @error('client_declaration_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">Update Form</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('form_type').addEventListener('change', function() {
        const partA = document.getElementById('part-a');
        const partB = document.getElementById('part-b');
        if (this.value === 'appointment') {
            partA.style.display = 'block';
            partB.style.display = 'none';
        } else {
            partA.style.display = 'none';
            partB.style.display = 'block';
        }
    });
    // Trigger change on page load
    document.getElementById('form_type').dispatchEvent(new Event('change'));
</script>
@endsection