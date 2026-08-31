<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Client Details Verification</title>
  <style>
    :root {
      --primary: #5b3dbd;
      --primary-dark: #43259e;
      --bg: #f5f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --border: #e5e7eb;
      --success: #14804a;
      --success-bg: #ecfdf3;
      --warning: #a15c00;
      --warning-bg: #fff7e6;
      --shadow: 0 8px 24px rgba(20, 25, 40, 0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.45;
    }
    .shell { max-width: 760px; margin: 0 auto; padding: 24px 14px 60px; }
    .header {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white; border-radius: 18px; padding: 24px; box-shadow: var(--shadow); margin-bottom: 18px;
    }
    .brand { font-weight: 800; font-size: 20px; margin-bottom: 8px; }
    .header h1 { margin: 0 0 8px; font-size: 28px; line-height: 1.2; }
    .header p { margin: 0; opacity: .92; }
    .notice {
      background: #eef2ff; border: 1px solid #dfe4ff; color: #3730a3;
      border-radius: 14px; padding: 14px 16px; margin-bottom: 18px; font-size: 14px;
    }
    .card {
      background: var(--card); border: 1px solid var(--border); border-radius: 18px;
      box-shadow: var(--shadow); margin-bottom: 18px; overflow: hidden;
    }
    .card-head {
      padding: 18px 20px; border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between; gap: 12px;
    }
    .card-head h2 { margin: 0; font-size: 20px; }
    .card-head span { font-size: 13px; color: var(--muted); }
    .field { padding: 18px 20px; border-bottom: 1px solid var(--border); }
    .field:last-child { border-bottom: 0; }
    .field-top { display: flex; justify-content: space-between; gap: 18px; align-items: flex-start; }
    .label { color: var(--muted); font-size: 13px; margin-bottom: 5px; }
    .value { font-size: 16px; font-weight: 700; word-break: break-word; }
    .actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; min-width: 210px; }
    button { border: 0; cursor: pointer; font: inherit; border-radius: 10px; padding: 9px 12px; font-weight: 700; }
    .btn-confirm { background: var(--success-bg); color: var(--success); border: 1px solid #b7ebc9; }
    .btn-change { background: white; color: var(--primary); border: 1px solid #cfc5f6; }
    .btn-primary { background: var(--primary); color: white; padding: 13px 18px; }
    .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid var(--border); }
    .edit-panel { display: none; margin-top: 14px; padding: 14px; border-radius: 12px; background: #fafaff; border: 1px solid #e5e1fb; }
    .edit-panel.show { display: block; }
    input, select, textarea {
      width: 100%; border: 1px solid #cfd4dc; border-radius: 10px; padding: 11px 12px; font: inherit; color: var(--text); background: white;
    }
    textarea { min-height: 88px; resize: vertical; }
    .edit-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
    .edit-actions { display: flex; gap: 8px; margin-top: 10px; }
    .status { display: none; margin-top: 12px; border-radius: 10px; padding: 10px 12px; font-size: 14px; font-weight: 700; }
    .status.confirmed { display: block; background: var(--success-bg); color: var(--success); }
    .status.changed { display: block; background: var(--warning-bg); color: var(--warning); }
    .summary-card { padding: 20px; }
    .summary-row { display: flex; justify-content: space-between; gap: 14px; padding: 10px 0; border-bottom: 1px dashed var(--border); }
    .summary-row:last-child { border-bottom: 0; }
    .submit-area { background: white; border: 1px solid var(--border); border-radius: 18px; box-shadow: var(--shadow); padding: 20px; position: sticky; bottom: 12px; }
    .checkbox-row { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 14px; }
    .checkbox-row input { width: auto; margin-top: 4px; }
    .submit-actions { display: flex; gap: 10px; }
    .submit-actions button { flex: 1; }
    .success-screen { min-height: 60vh; display: flex; align-items: center; justify-content: center; text-align: center; }
    .success-box { background: white; border: 1px solid var(--border); border-radius: 20px; box-shadow: var(--shadow); padding: 34px 24px; max-width: 560px; width: 100%; }
    .tick { width: 62px; height: 62px; border-radius: 50%; margin: 0 auto 16px; background: var(--success-bg); color: var(--success); display: grid; place-items: center; font-size: 34px; font-weight: 900; }
    .small { color: var(--muted); font-size: 13px; }
    .security { margin-top: 12px; font-size: 12px; color: var(--muted); text-align: center; }
    .errors { background: #fff1f0; color: #b42318; border: 1px solid #ffccc7; border-radius: 12px; padding: 12px 14px; margin-bottom: 18px; }
    @media (max-width: 640px) {
      .header h1 { font-size: 23px; }
      .field-top { flex-direction: column; }
      .actions { width: 100%; min-width: 0; justify-content: flex-start; }
      .actions button { flex: 1; }
      .submit-actions { flex-direction: column; }
    }
  </style>
</head>
<body>
@php
  $v = $values ?? [];
  $submitted = ! empty($submitted);
@endphp

@if($submitted)
  <section class="shell success-screen">
    <div class="success-box">
      <div class="tick">✓</div>
      <h1>Verification Submitted</h1>
      <p>Thank you, <strong>{{ $firstName }}</strong>. Your personal and visa details have been submitted successfully.</p>
      <p class="small">
        @if(($changedCount ?? 0) === 0)
          You confirmed all details as correct.
        @else
          You requested {{ $changedCount }} change(s) for our team to review.
        @endif
      </p>
      <p class="small">If you requested any changes, our team will review them before updating your record.</p>
    </div>
  </section>
@else
  <main class="shell" id="verificationScreen">
    <section class="header">
      <div class="brand">Bansal Immigration Consultants</div>
      <h1>Verify Your Personal & Visa Details</h1>
      <p>Hello <strong>{{ $firstName }}</strong>. Please review the information we currently have on your file.</p>
    </section>

    @if($errors->any())
      <div class="errors">{{ $errors->first() }}</div>
    @endif

    <div class="notice">
      Please select <strong>Confirm</strong> if a detail is correct. Select <strong>Request Change</strong> if anything needs to be updated. Any requested changes will be reviewed by our team before your CRM record is updated.
    </div>

    <section class="card">
      <div class="card-head"><h2>Personal Details</h2><span>Step 1 of 2</span></div>
      @include('public.partials.client_detail_verification_field', ['key' => 'full_name', 'label' => 'Full Name', 'value' => $v['full_name'] ?? 'N/A', 'input' => 'text', 'placeholder' => 'Enter correct full name', 'showNote' => true])
      @include('public.partials.client_detail_verification_field', ['key' => 'dob', 'label' => 'Date of Birth', 'value' => $v['dob'] ?? 'N/A', 'input' => 'date'])
      @include('public.partials.client_detail_verification_field', ['key' => 'gender', 'label' => 'Gender', 'value' => $v['gender'] ?? 'N/A', 'input' => 'select', 'options' => ['Female', 'Male', 'Other', 'Prefer not to say']])
      @include('public.partials.client_detail_verification_field', ['key' => 'marital_status', 'label' => 'Marital Status', 'value' => $v['marital_status'] ?? 'N/A', 'input' => 'select', 'options' => ['Never Married', 'Married', 'De Facto', 'Separated', 'Divorced', 'Widowed']])
      @include('public.partials.client_detail_verification_field', ['key' => 'email', 'label' => 'Email Address', 'value' => $v['email'] ?? 'N/A', 'input' => 'email', 'placeholder' => 'Enter correct email address'])
      @include('public.partials.client_detail_verification_field', ['key' => 'phone', 'label' => 'Mobile Number', 'value' => $v['phone'] ?? 'N/A', 'input' => 'tel', 'placeholder' => 'Enter correct mobile number'])
      @include('public.partials.client_detail_verification_field', ['key' => 'address', 'label' => 'Residential Address', 'value' => $v['address'] ?? 'N/A', 'input' => 'textarea', 'placeholder' => 'Enter correct residential address'])
    </section>

    <section class="card">
      <div class="card-head"><h2>Visa Details</h2><span>Step 2 of 2</span></div>
      @include('public.partials.client_detail_verification_field', ['key' => 'visa_type', 'label' => 'Current Visa Type / Status', 'value' => $v['visa_type'] ?? 'N/A', 'input' => 'text', 'placeholder' => 'e.g. Student 500 / Visitor 600 / Offshore'])
      @include('public.partials.client_detail_verification_field', ['key' => 'visa_expiry', 'label' => 'Visa Expiry Date', 'value' => $v['visa_expiry'] ?? 'N/A', 'input' => 'date'])
      @include('public.partials.client_detail_verification_field', ['key' => 'passport_country', 'label' => 'Country of Passport', 'value' => $v['passport_country'] ?? 'N/A', 'input' => 'text', 'placeholder' => 'Enter correct passport country'])
      @include('public.partials.client_detail_verification_field', ['key' => 'location_status', 'label' => 'Current Location', 'value' => $v['location_status'] ?? 'N/A', 'input' => 'select', 'options' => ['Onshore - Australia', 'Offshore - Outside Australia']])
    </section>

    <section class="card summary-card">
      <h2 style="margin-top:0">Verification Summary</h2>
      <div class="summary-row"><span>Confirmed fields</span><strong id="confirmedCount">0</strong></div>
      <div class="summary-row"><span>Requested changes</span><strong id="changedCount">0</strong></div>
      <div class="summary-row"><span>Still to review</span><strong id="pendingCount">11</strong></div>
    </section>

    <form method="POST" action="{{ $submitUrl }}" id="verificationForm" class="submit-area">
      @csrf
      <input type="hidden" name="fields_json" id="fieldsJson" />
      <div class="checkbox-row">
        <input type="checkbox" id="declaration" name="declaration" value="1" />
        <label for="declaration">
          I confirm that I have reviewed the above information and that the information confirmed or corrected by me is accurate to the best of my knowledge.
        </label>
      </div>
      <div class="submit-actions">
        <button type="submit" class="btn-primary">Submit Verification</button>
      </div>
      <div class="security">For your security, this personalised verification link should not be forwarded to anyone else.</div>
    </form>
  </main>

  <script>
    const fields = Array.from(document.querySelectorAll('.field'));

    function getField(button) { return button.closest('.field'); }

    function confirmField(button) {
      const field = getField(button);
      field.dataset.status = 'confirmed';
      field.dataset.newValue = '';
      field.querySelector('.edit-panel').classList.remove('show');
      const status = field.querySelector('.status');
      status.className = 'status confirmed';
      status.textContent = '✓ Confirmed as correct';
      updateSummary();
    }

    function openChange(button) {
      const panel = getField(button).querySelector('.edit-panel');
      panel.classList.add('show');
      const input = panel.querySelector('.new-value');
      if (input) input.focus();
    }

    function cancelChange(button) {
      getField(button).querySelector('.edit-panel').classList.remove('show');
    }

    function saveChange(button) {
      const field = getField(button);
      const input = field.querySelector('.new-value');
      const newValue = (input.value || '').trim();
      if (!newValue) {
        alert('Please enter or select the correct information before saving.');
        input.focus();
        return;
      }
      field.dataset.status = 'changed';
      field.dataset.newValue = newValue;
      field.querySelector('.edit-panel').classList.remove('show');
      const status = field.querySelector('.status');
      status.className = 'status changed';
      status.textContent = '⚠ Change requested: ' + newValue;
      updateSummary();
    }

    function updateSummary() {
      const confirmed = fields.filter(f => f.dataset.status === 'confirmed').length;
      const changed = fields.filter(f => f.dataset.status === 'changed').length;
      document.getElementById('confirmedCount').textContent = confirmed;
      document.getElementById('changedCount').textContent = changed;
      document.getElementById('pendingCount').textContent = fields.length - confirmed - changed;
    }

    document.getElementById('verificationForm').addEventListener('submit', function (event) {
      const pending = fields.filter(f => !f.dataset.status).length;
      if (pending > 0) {
        event.preventDefault();
        alert('Please review all personal and visa details before submitting. ' + pending + ' field(s) are still pending.');
        return;
      }
      if (!document.getElementById('declaration').checked) {
        event.preventDefault();
        alert('Please tick the declaration before submitting.');
        return;
      }
      const payload = fields.map(field => ({
        key: field.dataset.key,
        current_value: field.querySelector('.current-value').textContent.trim(),
        status: field.dataset.status === 'changed' ? 'change_requested' : 'confirmed',
        requested_value: field.dataset.newValue || null,
        note: (field.querySelector('.change-note') || {}).value || null
      }));
      document.getElementById('fieldsJson').value = JSON.stringify(payload);
    });

    updateSummary();
  </script>
@endif
</body>
</html>
