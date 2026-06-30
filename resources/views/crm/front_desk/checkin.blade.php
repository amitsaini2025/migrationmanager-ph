@extends('layouts.crm_client_detail')
@section('title', 'Front-Desk Check-In')

@section('content')
<style>
/* ── Wizard container ───────────────────────────────────── */
.fd-wizard-wrapper {
    max-width: 700px;
    margin: 0 auto;
    padding: 90px 15px 40px; /* 90px top clears the fixed 70px topbar */
}
.fd-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    border: 1px solid #e8eaed;
    overflow: hidden;
}
.fd-card-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d6a9f 100%);
    color: #fff;
    padding: 22px 28px;
}
.fd-card-header h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}
.fd-card-header p {
    margin: 4px 0 0;
    font-size: 0.85rem;
    opacity: .85;
}
.fd-card-body {
    padding: 28px;
}

/* ── Stepper ────────────────────────────────────────────── */
.fd-stepper {
    display: flex;
    align-items: center;
    margin-bottom: 28px;
    gap: 0;
}
.fd-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    position: relative;
}
.fd-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 14px;
    left: 55%;
    right: -45%;
    height: 2px;
    background: #dee2e6;
    z-index: 0;
}
.fd-step.done:not(:last-child)::after {
    background: #1e3a5f;
}
.fd-step-circle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #dee2e6;
    color: #868e96;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .8rem;
    position: relative;
    z-index: 1;
    transition: background .2s, color .2s;
}
.fd-step.done .fd-step-circle {
    background: #28a745;
    color: #fff;
}
.fd-step.active .fd-step-circle {
    background: #1e3a5f;
    color: #fff;
}
.fd-step-label {
    margin-top: 6px;
    font-size: .72rem;
    color: #868e96;
    text-align: center;
    white-space: nowrap;
}
.fd-step.active .fd-step-label { color: #1e3a5f; font-weight: 600; }
.fd-step.done  .fd-step-label { color: #28a745; }

/* ── Steps ──────────────────────────────────────────────── */
.fd-wizard-step { display: none; }
.fd-wizard-step.active { display: block; }

/* ── Match cards ────────────────────────────────────────── */
.fd-match-card {
    border: 2px solid #e8eaed;
    border-radius: 8px;
    padding: 14px 16px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    margin-bottom: 10px;
}
.fd-match-card:hover { border-color: #2d6a9f; background: #f0f6ff; }
.fd-match-card.selected { border-color: #1e3a5f; background: #e8f0fb; }
.fd-match-card .badge { font-size: .72rem; }

/* ── Confirm summary ────────────────────────────────────── */
.fd-summary-row {
    display: flex;
    gap: 8px;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: .9rem;
}
.fd-summary-row:last-child { border-bottom: none; }
.fd-summary-label { color: #6c757d; min-width: 130px; font-weight: 500; }
.fd-summary-value { color: #212529; font-weight: 600; }

/* ── Step titles & lead copy (WCAG-friendly contrast) ─────── */
.fd-wizard-wrapper .fd-step-title {
    color: #1a237e;
    font-weight: 700;
    letter-spacing: 0.03em;
}
.fd-wizard-wrapper .fd-lead-question {
    color: #212529;
    font-weight: 600;
    font-size: 1.05rem;
    line-height: 1.45;
}

/* ── Step 4: Yes / No — equal visual weight, always readable ─ */
.fd-appt-choices {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.fd-choice-btn {
    flex: 1 1 220px;
    min-width: min(100%, 200px);
    padding: 14px 18px;
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.35;
    border-radius: 10px;
    border: 2px solid;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease;
    text-align: center;
}
.fd-choice-btn:focus {
    outline: 3px solid rgba(25, 118, 210, 0.45);
    outline-offset: 2px;
}
.fd-choice-yes {
    background: #e3f2fd;
    border-color: #1976d2;
    color: #0d47a1;
}
.fd-choice-yes:hover {
    background: #bbdefb;
    border-color: #1565c0;
    color: #082c5a;
}
.fd-choice-yes.fd-choice--selected {
    background: #1565c0;
    border-color: #0d47a1;
    color: #fff;
    box-shadow: 0 3px 10px rgba(21, 101, 192, 0.35);
}
.fd-choice-yes.fd-choice--selected:hover {
    background: #0d47a1;
    color: #fff;
}
.fd-choice-no {
    background: #eceff1;
    border-color: #607d8b;
    color: #263238;
}
.fd-choice-no:hover {
    background: #cfd8dc;
    border-color: #546e7a;
    color: #1c2429;
}
.fd-choice-no.fd-choice--selected {
    background: #455a64;
    border-color: #37474f;
    color: #fff;
    box-shadow: 0 3px 10px rgba(69, 90, 100, 0.35);
}
.fd-choice-no.fd-choice--selected:hover {
    background: #37474f;
    color: #fff;
}

/* ── Primary actions in wizard (consistent, high contrast) ─ */
.fd-wizard-wrapper .fd-btn-action {
    background: #1565c0;
    border: 2px solid #0d47a1;
    color: #fff !important;
    font-weight: 600;
}
.fd-wizard-wrapper .fd-btn-action:hover:not(:disabled) {
    background: #0d47a1;
    border-color: #082c5a;
    color: #fff !important;
}
.fd-wizard-wrapper .fd-btn-action:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.fd-wizard-wrapper .fd-btn-confirm {
    background: #2e7d32;
    border: 2px solid #1b5e20;
    color: #fff !important;
    font-weight: 600;
}
.fd-wizard-wrapper .fd-btn-confirm:hover {
    background: #1b5e20;
    border-color: #145214;
    color: #fff !important;
}

.fd-wizard-wrapper .fd-btn-walkin {
    background: #fff;
    border: 2px solid #78909c;
    color: #37474f;
    font-weight: 600;
}
.fd-wizard-wrapper .fd-btn-walkin:hover {
    background: #eceff1;
    border-color: #546e7a;
    color: #263238;
}
.fd-wizard-wrapper .fd-btn-walkin.active {
    background: #fff8e1;
    border-color: #f9a825;
    color: #e65100;
    box-shadow: 0 0 0 2px rgba(249, 168, 37, 0.35);
}

/* ── Appointment cards ──────────────────────────────────── */
.fd-appt-card {
    border: 2px solid #e8eaed;
    border-radius: 8px;
    padding: 12px 16px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    margin-bottom: 8px;
}
.fd-appt-card:hover { border-color: #17a2b8; background: #f0fbff; }
.fd-appt-card.selected { border-color: #17a2b8; background: #d9f3fa; }

/* ── Success state ──────────────────────────────────────── */
.fd-success { text-align: center; padding: 40px 20px; }
.fd-success i { font-size: 3.5rem; color: #28a745; margin-bottom: 16px; }
.fd-success h5 { font-size: 1.4rem; font-weight: 700; color: #212529; margin-bottom: 8px; }
.fd-success p { color: #6c757d; }

/* ── Utilities ──────────────────────────────────────────── */
.fd-spinner { display: none; text-align: center; padding: 20px; }
.fd-alert-box { display: none; }
</style>

<div class="fd-wizard-wrapper">
    <div class="fd-card">
        <div class="fd-card-header">
            <h4>@icon('fa-clipboard-check', ['class' => 'mr-2'])Front-Desk Check-In</h4>
            <p>Record a client or walk-in arrival at the front desk</p>
        </div>
        <div class="fd-card-body">

            {{-- Stepper --}}
            <div class="fd-stepper" id="fdStepper">
                <div class="fd-step active" data-step="1">
                    <div class="fd-step-circle">1</div>
                    <div class="fd-step-label">Contact</div>
                </div>
                <div class="fd-step" data-step="2">
                    <div class="fd-step-circle">2</div>
                    <div class="fd-step-label">Match</div>
                </div>
                <div class="fd-step" data-step="3">
                    <div class="fd-step-circle">3</div>
                    <div class="fd-step-label">Confirm</div>
                </div>
                <div class="fd-step" data-step="4">
                    <div class="fd-step-circle">4</div>
                    <div class="fd-step-label">Appointment</div>
                </div>
                <div class="fd-step" data-step="5">
                    <div class="fd-step-circle">5</div>
                    <div class="fd-step-label">Reason</div>
                </div>
            </div>

            <div class="fd-alert-box alert alert-danger" id="fdGlobalAlert" role="alert"></div>

            {{-- ── STEP 1: Phone + Email ─────────────────────────── --}}
            <div class="fd-wizard-step active" id="fdStep1">
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 1 — Contact Details</h6>
                <div class="form-group">
                    <label for="fdPhone" class="font-weight-600">Phone <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control form-control-lg" id="fdPhone" placeholder="e.g. 0412 345 678" maxlength="20" autocomplete="off">
                    <div class="invalid-feedback" id="fdPhoneError"></div>
                </div>
                <div class="form-group">
                    <label for="fdEmail" class="font-weight-600">Email <span class="text-muted">(optional — narrows results)</span></label>
                    <input type="email" class="form-control" id="fdEmail" placeholder="e.g. john@example.com" autocomplete="off">
                </div>
                <div class="text-right">
                    <button type="button" class="btn btn-lg px-5 fd-btn-action" id="fdLookupBtn">
                        @icon('fa-search', ['class' => 'mr-2'])Look Up
                    </button>
                </div>
                <div class="fd-spinner mt-3" id="fdLookupSpinner">
                    <div class="spinner-border text-primary" role="status"><span class="sr-only">Searching…</span></div>
                    <p class="mt-2 text-muted">Searching CRM…</p>
                </div>
            </div>

            {{-- ── STEP 2: Match selection ───────────────────────── --}}
            <div class="fd-wizard-step" id="fdStep2">
                <h6 class="fd-step-title mb-1 text-uppercase small">Step 2 — Select Match</h6>
                <p class="text-muted small mb-3" id="fdMatchSubtitle"></p>
                <div id="fdMatchList"></div>

                <div class="border-top pt-3 mt-2">
                    <p class="text-muted small mb-2">Not in the list?</p>
                    <button type="button" class="btn btn-sm fd-btn-walkin" id="fdWalkInBtn">
                        @icon('fa-user-slash', ['class' => 'mr-1'])Continue as Walk-In (no CRM record)
                    </button>
                </div>

                <div class="text-right mt-4">
                    <button class="btn btn-light mr-2" id="fdStep2Back">@icon('fa-arrow-left', ['class' => 'mr-1'])Back</button>
                    <button type="button" class="btn fd-btn-action" id="fdStep2Next" disabled>
                        Confirm Selection @icon('fa-arrow-right', ['class' => 'ml-1'])
                    </button>
                </div>
            </div>

            {{-- ── STEP 2b: New client? (zero-match branch) ─────── --}}
            <div class="fd-wizard-step" id="fdStepNewClient">
                <h6 class="fd-step-title mb-2 text-uppercase small">Not Found in Our System</h6>
                <p class="fd-lead-question mb-4">Are you visiting as a new client today?</p>

                <div class="fd-appt-choices mb-4">
                    <button type="button" class="fd-choice-btn fd-choice-yes" id="fdNewClientYes">
                        @icon('fa-user-plus', ['class' => 'mr-2', 'aria-hidden' => 'true'])Yes — I'm a new client
                    </button>
                    <button type="button" class="fd-choice-btn fd-choice-no" id="fdNewClientNo">
                        @icon('fa-user-check', ['class' => 'mr-2', 'aria-hidden' => 'true'])No — I already have a file
                    </button>
                </div>

                <div class="text-right mt-2">
                    <button class="btn btn-light" id="fdStepNewClientBack">@icon('fa-arrow-left', ['class' => 'mr-1'])Back</button>
                </div>
            </div>

            {{-- ── STEP 2c: Minimal lead form ────────────────────── --}}
            <div class="fd-wizard-step" id="fdStepLeadForm">
                <h6 class="fd-step-title mb-3 text-uppercase small">Your Details</h6>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fdLeadFirstName" maxlength="100" autocomplete="off">
                            <div class="invalid-feedback" id="fdLeadFirstNameError"></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">Last Name</label>
                            <input type="text" class="form-control" id="fdLeadLastName" maxlength="100" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">Phone</label>
                            <input type="text" class="form-control" id="fdLeadPhoneDisplay" readonly>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="font-weight-600">Email</label>
                            <input type="text" class="form-control" id="fdLeadEmailDisplay" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-600">Reason for Visit <span class="text-danger">*</span></label>
                    <select class="form-control" id="fdLeadVisitReason">
                        <option value="">— Select reason —</option>
                        @foreach($visitReasons as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" id="fdLeadVisitReasonError"></div>
                </div>

                <div class="form-group" id="fdLeadVisitNotesGroup">
                    <label class="font-weight-600">
                        Notes
                        <span id="fdLeadNotesRequired" class="text-danger" style="display:none;">*</span>
                    </label>
                    <textarea class="form-control" id="fdLeadVisitNotes" rows="3" placeholder="Additional notes…" maxlength="2000"></textarea>
                    <div class="invalid-feedback" id="fdLeadVisitNotesError"></div>
                </div>

                <div class="text-right mt-4">
                    <button class="btn btn-light mr-2" id="fdStepLeadFormBack">@icon('fa-arrow-left', ['class' => 'mr-1'])Back</button>
                    <button type="button" class="btn btn-lg px-5 fd-btn-confirm" id="fdLeadSubmitBtn">
                        @icon('fa-paper-plane', ['class' => 'mr-2'])Submit Check-In
                    </button>
                </div>
                <div class="fd-spinner mt-3" id="fdLeadSubmitSpinner">
                    <div class="spinner-border text-success" role="status"><span class="sr-only">Saving…</span></div>
                    <p class="mt-2 text-muted">Creating record and saving check-in…</p>
                </div>
            </div>

            {{-- ── STEP 2d: Not a client — dead end ─────────────── --}}
            <div class="fd-wizard-step" id="fdStepNotClient">
                <div class="fd-success" style="padding: 30px 20px;">
                    @icon('fa-hand-paper', ['style' => 'font-size:3rem; color:#607d8b; margin-bottom:16px;'])
                    <h5 style="color:#212529;">Please speak with our receptionist</h5>
                    <p class="text-muted mb-4">Our front-desk team will be happy to help you locate your file.</p>
                    <button type="button" class="btn fd-btn-action" id="fdNotClientStartOver">
                        @icon('fa-redo', ['class' => 'mr-2'])Start Over
                    </button>
                </div>
            </div>

            {{-- ── STEP 3: Confirm details ───────────────────────── --}}
            <div class="fd-wizard-step" id="fdStep3">
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 3 — Confirm Details</h6>
                <div id="fdConfirmSummary"></div>
                <div class="text-right mt-4">
                    <button class="btn btn-light mr-2" id="fdStep3Back">@icon('fa-arrow-left', ['class' => 'mr-1'])Back</button>
                    <button type="button" class="btn fd-btn-confirm" id="fdStep3Next">
                        Details Correct @icon('fa-check', ['class' => 'ml-1'])
                    </button>
                </div>
            </div>

            {{-- ── STEP 4: Has appointment? ──────────────────────── --}}
            <div class="fd-wizard-step" id="fdStep4">
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 4 — Appointment</h6>
                <p class="fd-lead-question mb-4">Does the visitor have a scheduled appointment today?</p>

                <div class="fd-appt-choices mb-4">
                    <button type="button" class="fd-choice-btn fd-choice-yes" id="fdHasApptYes">
                        @icon('fa-calendar-check', ['class' => 'mr-2', 'aria-hidden' => 'true'])Yes, has appointment
                    </button>
                    <button type="button" class="fd-choice-btn fd-choice-no" id="fdHasApptNo">
                        @icon('fa-calendar-times', ['class' => 'mr-2', 'aria-hidden' => 'true'])No appointment
                    </button>
                </div>

                {{-- Appointment list (shown when Yes) --}}
                <div id="fdApptSection" style="display:none;">
                    <div class="fd-spinner" id="fdApptSpinner">
                        <div class="spinner-border text-info" role="status"><span class="sr-only">Loading…</span></div>
                    </div>
                    <div id="fdApptList"></div>
                    <p class="text-muted small mt-2" id="fdApptNoneMsg" style="display:none;">
                        No appointments found for this visitor today. You can still continue.
                    </p>
                </div>

                <div class="text-right mt-4">
                    <button class="btn btn-light mr-2" id="fdStep4Back">@icon('fa-arrow-left', ['class' => 'mr-1'])Back</button>
                    <button type="button" class="btn fd-btn-action" id="fdStep4Next" disabled>
                        Continue @icon('fa-arrow-right', ['class' => 'ml-1'])
                    </button>
                </div>
            </div>

            {{-- ── STEP 5: Reason ────────────────────────────────── --}}
            <div class="fd-wizard-step" id="fdStep5">
                <h6 class="fd-step-title mb-3 text-uppercase small">Step 5 — Visit Reason</h6>

                <div class="form-group">
                    <label class="font-weight-600">Reason for Visit <span class="text-muted">(optional)</span></label>
                    <select class="form-control" id="fdVisitReason">
                        <option value="">— Select reason —</option>
                        @foreach($visitReasons as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="fdVisitNotesGroup">
                    <label class="font-weight-600">
                        Notes
                        <span id="fdNotesRequired" class="text-danger" style="display:none;">*</span>
                    </label>
                    <textarea class="form-control" id="fdVisitNotes" rows="3" placeholder="Additional notes…" maxlength="2000"></textarea>
                    <div class="invalid-feedback" id="fdVisitNotesError"></div>
                </div>

                <div class="text-right mt-4">
                    <button class="btn btn-light mr-2" id="fdStep5Back">@icon('fa-arrow-left', ['class' => 'mr-1'])Back</button>
                    <button type="button" class="btn btn-lg px-5 fd-btn-confirm" id="fdSubmitBtn">
                        @icon('fa-paper-plane', ['class' => 'mr-2'])Submit Check-In
                    </button>
                </div>
                <div class="fd-spinner mt-3" id="fdSubmitSpinner">
                    <div class="spinner-border text-success" role="status"><span class="sr-only">Saving…</span></div>
                    <p class="mt-2 text-muted">Saving check-in…</p>
                </div>
            </div>

            {{-- ── SUCCESS ───────────────────────────────────────── --}}
            <div class="fd-wizard-step" id="fdStepSuccess">
                <div class="fd-success">
                    @icon('fa-check-circle')
                    <h5>Check-In Recorded!</h5>
                    <p id="fdSuccessMsg" class="mb-4"></p>
                    <button type="button" class="btn fd-btn-action" id="fdStartOver">
                        @icon('fa-redo', ['class' => 'mr-2'])New Check-In
                    </button>
                    <a href="{{ route('officevisits.waiting') }}" class="btn btn-outline-secondary ml-2">
                        @icon('fa-list', ['class' => 'mr-2'])Office Visits
                    </a>
                </div>
            </div>

        </div>{{-- /fd-card-body --}}
    </div>{{-- /fd-card --}}
</div>

<script>
(function () {
    'use strict';

    var CSRF  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var BASE  = '{{ url("/front-desk/checkin") }}';

    /* ── State ──────────────────────────────────────────────── */
    var state = {
        phone:            '',
        phoneNormalized:  '',
        email:            '',
        adminId:          null,   // matched admin id (or null = walk-in)
        adminType:        null,   // 'client' | 'lead' | null
        adminName:        '',
        adminEmail:       '',
        adminPhone:       '',
        appointmentId:    null,
        claimedAppointment: false,
        visitReason:      '',
        visitNotes:       '',
        currentStep:      1,
        path:             null,   // null | 'existing_match' | 'new_lead' | 'not_client'
    };

    /* ── DOM helpers ────────────────────────────────────────── */
    function $(sel) { return document.querySelector(sel); }
    // Always set an explicit 'block' so CSS-class-hidden elements (e.g. .fd-spinner) are shown.
    function show(el, display) { if (el) el.style.display = display || 'block'; }
    function hide(el) { if (el) el.style.display = 'none'; }
    function showAlert(msg) {
        var el = $('#fdGlobalAlert');
        el.textContent = msg;
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function hideAlert() { $('#fdGlobalAlert').style.display = 'none'; }

    /* ── Stepper ────────────────────────────────────────────── */
    var OFF_PATH_STEPS = ['new-client', 'lead-form', 'not-client'];

    function setStep(n) {
        state.currentStep = n;
        // Activate wizard panel
        document.querySelectorAll('.fd-wizard-step').forEach(function (el) {
            el.classList.remove('active');
        });
        var panelMap = {
            'success':    '#fdStepSuccess',
            'new-client': '#fdStepNewClient',
            'lead-form':  '#fdStepLeadForm',
            'not-client': '#fdStepNotClient',
        };
        var panel = panelMap[n] || ('#fdStep' + n);
        var el = document.querySelector(panel);
        if (el) el.classList.add('active');

        // Hide stepper for off-path panels; show for main numbered steps
        var stepper = document.getElementById('fdStepper');
        if (stepper) {
            stepper.style.display = (OFF_PATH_STEPS.indexOf(n) !== -1) ? 'none' : '';
        }

        // Update stepper circles
        document.querySelectorAll('.fd-step').forEach(function (step) {
            var sn = parseInt(step.getAttribute('data-step'), 10);
            step.classList.remove('active', 'done');
            if (typeof n === 'number') {
                if (sn < n)  step.classList.add('done');
                if (sn === n) step.classList.add('active');
            }
        });
        hideAlert();
    }

    /* ── AJAX helper ────────────────────────────────────────── */
    function post(url, data) {
        return fetch(url, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        }).then(function (r) {
            return r.text().then(function (text) {
                var j = {};
                try {
                    j = text ? JSON.parse(text) : {};
                } catch (e) {
                    return Promise.reject(new Error('Invalid server response.'));
                }
                if (!r.ok) {
                    var msg = j.message || j.error;
                    if (!msg && j.errors) {
                        msg = Object.values(j.errors).flat().join(' ');
                    }
                    if (!msg) {
                        msg = 'Request failed (' + r.status + ').';
                    }
                    var err = new Error(msg);
                    err.payload = j;
                    err.status = r.status;
                    return Promise.reject(err);
                }
                return j;
            });
        });
    }

    /* ── Step 1: Lookup ─────────────────────────────────────── */
    $('#fdLookupBtn').addEventListener('click', function () {
        var phone = $('#fdPhone').value.trim();
        if (!phone || phone.length < 6) {
            $('#fdPhone').classList.add('is-invalid');
            $('#fdPhoneError').textContent = 'Please enter a valid phone number.';
            return;
        }
        $('#fdPhone').classList.remove('is-invalid');
        state.phone = phone;
        state.email = $('#fdEmail').value.trim();

        show($('#fdLookupSpinner'));
        $('#fdLookupBtn').disabled = true;

        post(BASE + '/lookup', { phone: phone, email: state.email })
            .then(function (data) {
                hide($('#fdLookupSpinner'));
                $('#fdLookupBtn').disabled = false;

                if (data.error) { showAlert(data.error); return; }

                state.phoneNormalized = data.phone_normalized || '';
                // renderMatches calls setStep itself (either 2 or 'new-client')
                renderMatches(data.matches || []);
            })
            .catch(function (err) {
                hide($('#fdLookupSpinner'));
                $('#fdLookupBtn').disabled = false;
                showAlert(err && err.message ? err.message : 'Network error — please try again.');
            });
    });

    $('#fdPhone').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') $('#fdLookupBtn').click();
    });

    /* ── Step 2: Render matches ─────────────────────────────── */
    // NOTE: this function is responsible for calling setStep — the lookup
    // handler must NOT call setStep(2) after this, as the zero-match branch
    // routes to 'new-client' and an unconditional setStep(2) would override it.
    function renderMatches(matches) {
        var container = $('#fdMatchList');
        container.innerHTML = '';

        var subtitle = $('#fdMatchSubtitle');
        if (matches.length === 0) {
            // No CRM record — ask if they are a new client (off-path panel)
            setStep('new-client');
            return;
        }
        subtitle.textContent = matches.length + ' record' + (matches.length > 1 ? 's' : '') + ' found — select one or continue as walk-in.';

        matches.forEach(function (m) {
            var div = document.createElement('div');
            div.className = 'fd-match-card';
            div.setAttribute('data-id', m.id);
            div.setAttribute('data-type', m.type);
            div.setAttribute('data-name', m.name || '');
            div.setAttribute('data-email', m.email || '');
            div.setAttribute('data-phone', m.phone || '');

            var badge = m.type === 'client'
                ? '<span class="badge badge-success">Client</span>'
                : '<span class="badge badge-warning">Lead</span>';

            div.innerHTML = '<div class="d-flex justify-content-between align-items-start">' +
                '<div>' +
                    '<strong>' + escHtml(m.name || 'Unknown') + '</strong> ' + badge +
                    (m.is_company && m.company_name ? '<span class="text-muted small ml-1">(' + escHtml(m.company_name) + ')</span>' : '') +
                    '<br><small class="text-muted">' + escHtml(m.email || '—') + ' &bull; ' + escHtml(m.phone || '—') + '</small>' +
                '</div>' +
                '<span data-checkmark style="display:none;">' + crmI('fas fa-check-circle', { class: 'text-primary mt-1' }) + '</span>' +
                '</div>';

            div.addEventListener('click', function () {
                document.querySelectorAll('.fd-match-card').forEach(function (c) {
                    c.classList.remove('selected');
                    c.querySelector('[data-checkmark]').style.display = 'none';
                });
                div.classList.add('selected');
                div.querySelector('[data-checkmark]').style.display = '';
                state.adminId    = parseInt(m.id, 10);
                state.adminType  = m.type;
                state.adminName  = m.name || '';
                state.adminEmail = m.email || '';
                state.adminPhone = m.phone || '';
                state.path = 'existing_match';
                $('#fdStep2Next').disabled = false;
                $('#fdWalkInBtn').classList.remove('active');
            });

            container.appendChild(div);
        });

        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons(container);
        }

        setStep(2);
    }

    /* Walk-in selection (when matches exist but visitor is not listed) */
    $('#fdWalkInBtn').addEventListener('click', function () {
        document.querySelectorAll('.fd-match-card').forEach(function (c) {
            c.classList.remove('selected');
            c.querySelector('[data-checkmark]').style.display = 'none';
        });
        state.adminId   = null;
        state.adminType = null;
        state.adminName = 'Walk-in';
        state.adminEmail = '';
        state.adminPhone = '';
        state.path = 'existing_match';
        $('#fdWalkInBtn').classList.toggle('active');
        $('#fdStep2Next').disabled = false;
    });

    $('#fdStep2Next').addEventListener('click', function () { buildConfirm(); setStep(3); });
    $('#fdStep2Back').addEventListener('click', function () { setStep(1); });

    /* ── Step 2b: New client? ───────────────────────────────── */
    $('#fdNewClientYes').addEventListener('click', function () {
        state.path = 'new_lead';
        // Pre-fill the read-only phone/email fields from step-1 state
        $('#fdLeadPhoneDisplay').value = state.phone;
        $('#fdLeadEmailDisplay').value = state.email || '—';
        // Clear any previous lead-form values and validation state
        $('#fdLeadFirstName').value          = '';
        $('#fdLeadLastName').value           = '';
        $('#fdLeadVisitReason').value        = '';
        $('#fdLeadVisitNotes').value         = '';
        $('#fdLeadFirstName').classList.remove('is-invalid');
        $('#fdLeadVisitReason').classList.remove('is-invalid');
        $('#fdLeadVisitNotes').classList.remove('is-invalid');
        $('#fdLeadFirstNameError').textContent    = '';
        $('#fdLeadVisitReasonError').textContent  = '';
        $('#fdLeadVisitNotesError').textContent   = '';
        hide($('#fdLeadNotesRequired'));
        setStep('lead-form');
    });

    $('#fdNewClientNo').addEventListener('click', function () {
        state.path = 'not_client';
        setStep('not-client');
    });

    $('#fdStepNewClientBack').addEventListener('click', function () {
        state.path = null;
        setStep(1);
    });

    /* ── Step 2c: Lead form ─────────────────────────────────── */
    $('#fdLeadVisitReason').addEventListener('change', function () {
        var isOther = this.value === 'other';
        if (isOther) {
            show($('#fdLeadNotesRequired'));
        } else {
            hide($('#fdLeadNotesRequired'));
            $('#fdLeadVisitNotes').classList.remove('is-invalid');
            $('#fdLeadVisitNotesError').textContent = '';
        }
    });

    $('#fdStepLeadFormBack').addEventListener('click', function () {
        setStep('new-client');
    });

    $('#fdLeadSubmitBtn').addEventListener('click', function () {
        var firstName = $('#fdLeadFirstName').value.trim();
        var reason    = $('#fdLeadVisitReason').value;
        var notes     = $('#fdLeadVisitNotes').value.trim();
        var valid     = true;

        if (!firstName) {
            $('#fdLeadFirstName').classList.add('is-invalid');
            $('#fdLeadFirstNameError').textContent = 'First name is required.';
            valid = false;
        } else {
            $('#fdLeadFirstName').classList.remove('is-invalid');
        }

        if (!reason) {
            $('#fdLeadVisitReason').classList.add('is-invalid');
            $('#fdLeadVisitReasonError').textContent = 'Please select a reason for the visit.';
            valid = false;
        } else {
            $('#fdLeadVisitReason').classList.remove('is-invalid');
        }

        if (reason === 'other' && !notes) {
            $('#fdLeadVisitNotes').classList.add('is-invalid');
            $('#fdLeadVisitNotesError').textContent = 'Notes are required when selecting "Other".';
            valid = false;
        } else {
            $('#fdLeadVisitNotes').classList.remove('is-invalid');
        }

        if (!valid) { return; }

        show($('#fdLeadSubmitSpinner'));
        $('#fdLeadSubmitBtn').disabled = true;

        post(BASE + '/create-lead', {
            first_name:   firstName,
            last_name:    $('#fdLeadLastName').value.trim() || null,
            phone:        state.phone,
            email:        state.email || null,
            visit_reason: reason || null,
            visit_notes:  notes  || null,
        }).then(function (data) {
            hide($('#fdLeadSubmitSpinner'));
            $('#fdLeadSubmitBtn').disabled = false;

            if (data.success) {
                var msg = 'Check-in #' + data.check_in_id + ' saved for ' + (data.lead_name || 'new lead') + '.';
                if (data.notified_staff) {
                    msg += ' Notification sent to ' + data.notified_staff + '.';
                }
                $('#fdSuccessMsg').textContent = msg;
                setStep('success');
            } else {
                showAlert(data.message || 'Could not save check-in. Please try again.');
            }
        }).catch(function (err) {
            hide($('#fdLeadSubmitSpinner'));
            $('#fdLeadSubmitBtn').disabled = false;
            showAlert(err && err.message ? err.message : 'Network error — please try again.');
        });
    });

    /* ── Step 2d: Not a client — dead end ───────────────────── */
    $('#fdNotClientStartOver').addEventListener('click', function () { resetWizard(); });

    /* ── Step 3: Confirm ────────────────────────────────────── */
    function buildConfirm() {
        var rows = [
            ['Phone entered', state.phone],
            ['Email entered', state.email || '—'],
        ];
        if (state.adminId) {
            rows.push(['CRM Match', state.adminName + ' (' + state.adminType + ')']);
            rows.push(['CRM Email', state.adminEmail || '—']);
            rows.push(['CRM Phone', state.adminPhone || '—']);
        } else {
            rows.push(['CRM Match', 'Walk-in (no record)']);
        }

        var html = rows.map(function (r) {
            return '<div class="fd-summary-row">' +
                '<span class="fd-summary-label">' + escHtml(r[0]) + '</span>' +
                '<span class="fd-summary-value">' + escHtml(r[1]) + '</span>' +
                '</div>';
        }).join('');

        $('#fdConfirmSummary').innerHTML = html;
    }

    $('#fdStep3Next').addEventListener('click', function () { setStep(4); loadAppointmentSection(); });
    $('#fdStep3Back').addEventListener('click', function () { setStep(2); });

    /* ── Step 4: Appointment ────────────────────────────────── */
    function loadAppointmentSection() {
        // Reset
        hide($('#fdApptSection'));
        $('#fdStep4Next').disabled = true;
        document.querySelectorAll('.fd-appt-card').forEach(function(c){ c.remove(); });
        $('#fdHasApptYes').classList.remove('fd-choice--selected');
        $('#fdHasApptNo').classList.remove('fd-choice--selected');
        state.appointmentId = null;
        state.claimedAppointment = false;
    }

    $('#fdHasApptYes').addEventListener('click', function () {
        $('#fdHasApptYes').classList.add('fd-choice--selected');
        $('#fdHasApptNo').classList.remove('fd-choice--selected');
        state.claimedAppointment = true;

        show($('#fdApptSection'), 'block');

        if (!state.adminId) {
            $('#fdApptList').innerHTML =
                '<p class="text-muted">' + crmI('fas fa-info-circle', { class: 'mr-1' }) +
                'Walk-in visitor — no CRM record to match an appointment against. ' +
                'The visit will still be recorded.</p>';
            if (typeof refreshLucideIcons === 'function') {
                refreshLucideIcons($('#fdApptList'));
            }
            hide($('#fdApptSpinner'));
            hide($('#fdApptNoneMsg'));
            $('#fdStep4Next').disabled = false;
            return;
        }

        show($('#fdApptSpinner'), 'block');
        hide($('#fdApptNoneMsg'));
        $('#fdApptList').innerHTML = '';

        post(BASE + '/appointments', { admin_id: state.adminId })
            .then(function (data) {
                hide($('#fdApptSpinner'));
                var appts = data.appointments || [];
                if (appts.length === 0) {
                    show($('#fdApptNoneMsg'));
                    $('#fdStep4Next').disabled = false;
                    return;
                }
                renderAppointments(appts);
            })
            .catch(function (err) {
                hide($('#fdApptSpinner'));
                showAlert(err && err.message ? err.message : 'Could not load appointments. You may continue.');
                $('#fdStep4Next').disabled = false;
            });
    });

    $('#fdHasApptNo').addEventListener('click', function () {
        $('#fdHasApptNo').classList.add('fd-choice--selected');
        $('#fdHasApptYes').classList.remove('fd-choice--selected');
        state.claimedAppointment = false;
        state.appointmentId = null;
        hide($('#fdApptSection'));
        $('#fdStep4Next').disabled = false;
    });

    function renderAppointments(appts) {
        var container = $('#fdApptList');
        container.innerHTML = '<p class="font-weight-600 mb-2">Today\'s appointments:</p>';

        appts.forEach(function (a) {
            var div = document.createElement('div');
            div.className = 'fd-appt-card';
            div.setAttribute('data-id', a.id);

            var statusBadge = {
                confirmed: 'success', pending: 'warning', completed: 'info'
            }[a.status] || 'secondary';

            div.innerHTML = '<div class="d-flex justify-content-between align-items-center">' +
                '<div>' +
                    '<strong>' + escHtml(a.datetime || '—') + '</strong>' +
                    ' <span class="badge badge-' + statusBadge + '">' + escHtml(a.status) + '</span>' +
                    '<br><small class="text-muted">Consultant: ' + escHtml(a.consultant || '—') + ' &bull; ' + escHtml(a.location || '—') + '</small>' +
                '</div>' +
                '<span data-checkmark style="display:none;">' + crmI('fas fa-check-circle', { class: 'text-info' }) + '</span>' +
                '</div>';

            div.addEventListener('click', function () {
                document.querySelectorAll('.fd-appt-card').forEach(function (c) {
                    c.classList.remove('selected');
                    c.querySelector('[data-checkmark]').style.display = 'none';
                });
                div.classList.add('selected');
                div.querySelector('[data-checkmark]').style.display = '';
                state.appointmentId = parseInt(a.id, 10);
                $('#fdStep4Next').disabled = false;
            });

            container.appendChild(div);
        });

        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons(container);
        }

        // Allow proceeding without selecting one
        var skipP = document.createElement('p');
        skipP.className = 'text-muted small mt-2';
        skipP.innerHTML = 'Select an appointment above or click <strong>Continue</strong> to proceed without linking one.';
        container.appendChild(skipP);
        $('#fdStep4Next').disabled = false;
    }

    $('#fdStep4Next').addEventListener('click', function () { setStep(5); });
    $('#fdStep4Back').addEventListener('click', function () { state.appointmentId = null; state.claimedAppointment = false; setStep(3); });

    /* ── Step 5: Reason ─────────────────────────────────────── */
    $('#fdVisitReason').addEventListener('change', function () {
        var isOther = this.value === 'other';
        if (isOther) {
            show($('#fdNotesRequired'));
        } else {
            hide($('#fdNotesRequired'));
            $('#fdVisitNotes').classList.remove('is-invalid');
        }
    });

    $('#fdSubmitBtn').addEventListener('click', function () {
        var reason = $('#fdVisitReason').value;
        var notes  = $('#fdVisitNotes').value.trim();

        if (reason === 'other' && !notes) {
            $('#fdVisitNotes').classList.add('is-invalid');
            $('#fdVisitNotesError').textContent = 'Notes are required when selecting "Other".';
            return;
        }
        $('#fdVisitNotes').classList.remove('is-invalid');

        state.visitReason = reason;
        state.visitNotes  = notes;

        show($('#fdSubmitSpinner'));
        $('#fdSubmitBtn').disabled = true;

        post(BASE + '/submit', {
            phone:               state.phone,
            email:               state.email || null,
            admin_id:            state.adminId,
            admin_type:          state.adminType,
            appointment_id:      state.appointmentId,
            claimed_appointment: state.claimedAppointment,
            visit_reason:        state.visitReason || null,
            visit_notes:         state.visitNotes  || null,
        }).then(function (data) {
            hide($('#fdSubmitSpinner'));
            $('#fdSubmitBtn').disabled = false;

            if (data.success) {
                var msg = 'Check-in #' + data.check_in_id + ' saved.';
                if (data.notified_staff) {
                    msg += ' Notification sent to ' + data.notified_staff + '.';
                }
                $('#fdSuccessMsg').textContent = msg;
                setStep('success');
            } else {
                showAlert(data.message || 'Could not save check-in. Please try again.');
            }
        }).catch(function (err) {
            hide($('#fdSubmitSpinner'));
            $('#fdSubmitBtn').disabled = false;
            showAlert(err && err.message ? err.message : 'Network error — please try again.');
        });
    });

    $('#fdStep5Back').addEventListener('click', function () { setStep(4); });

    /* ── Start over ─────────────────────────────────────────── */
    function resetWizard() {
        state = {
            phone: '', phoneNormalized: '', email: '',
            adminId: null, adminType: null, adminName: '', adminEmail: '', adminPhone: '',
            appointmentId: null, claimedAppointment: false,
            visitReason: '', visitNotes: '', currentStep: 1,
            path: null,
        };
        $('#fdPhone').value           = '';
        $('#fdEmail').value           = '';
        $('#fdVisitReason').value     = '';
        $('#fdVisitNotes').value      = '';
        $('#fdLeadFirstName').value   = '';
        $('#fdLeadLastName').value    = '';
        $('#fdLeadVisitReason').value = '';
        $('#fdLeadVisitNotes').value  = '';
        $('#fdMatchList').innerHTML   = '';
        $('#fdApptList').innerHTML    = '';
        $('#fdStep2Next').disabled    = true;
        $('#fdStep4Next').disabled    = true;
        setStep(1);
    }

    $('#fdStartOver').addEventListener('click', function () { resetWizard(); });

    /* ── XSS helper ─────────────────────────────────────────── */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})();
</script>
@endsection
