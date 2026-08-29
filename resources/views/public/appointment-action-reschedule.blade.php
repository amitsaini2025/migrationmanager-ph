@extends('public.appointment-action-layout')

@section('content')
  <div class="msg info">Choose a new date (Monday–Thursday, from tomorrow onwards) and an open time slot. Meeting type cannot be changed here.</div>
  <div class="detail"><strong>Current date:</strong> {{ $appointmentDate }}</div>
  <div class="detail"><strong>Current time:</strong> {{ $appointmentTime }}</div>
  <div class="detail"><strong>Type:</strong> {{ $meetingTypeLabel }}</div>

  @if($errors->any())
    <div class="errors">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $submitUrl }}" id="reschedule-form">
    @csrf
    <label for="appointment_date">New date</label>
    <input type="date" id="appointment_date" name="appointment_date" min="{{ $minRescheduleDate }}" value="{{ old('appointment_date') }}" required>
    <input type="hidden" id="appointment_time" name="appointment_time" value="{{ old('appointment_time') }}">
    <label>Open slots</label>
    <div id="slots">Select a date to see open slots.</div>
    <div class="actions">
      <button class="btn-primary" type="submit" id="reschedule-submit" disabled>Reschedule</button>
    </div>
  </form>
@endsection

@section('scripts')
<script>
(function () {
  const dateInput = document.getElementById('appointment_date');
  const timeInput = document.getElementById('appointment_time');
  const slotsBox = document.getElementById('slots');
  const submitBtn = document.getElementById('reschedule-submit');
  const slotsUrl = @json($slotsUrl);
  const availabilityUrl = @json($availabilityUrl);
  const closedWeekdays = @json($closedWeekdays);
  const minRescheduleDate = @json($minRescheduleDate);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  let disabledWeeks = closedWeekdays.slice();
  let disabledDates = [];

  function setSubmitEnabled() {
    submitBtn.disabled = !dateInput.value || !timeInput.value;
  }

  function selectedDateLabel(isoDate) {
    const parts = isoDate.split('-');
    if (parts.length !== 3) {
      return '';
    }
    return parts[2] + '/' + parts[1] + '/' + parts[0];
  }

  function mergeClosedWeekdays(weeks) {
    const merged = (weeks || []).slice();
    closedWeekdays.forEach(function (day) {
      if (merged.indexOf(day) === -1) {
        merged.push(day);
      }
    });
    return merged;
  }

  function isBeforeMinDate(isoDate) {
    return !isoDate || isoDate < minRescheduleDate;
  }

  function isDateClosed(isoDate) {
    if (isBeforeMinDate(isoDate)) {
      return true;
    }
    const parsed = new Date(isoDate + 'T12:00:00');
    if (Number.isNaN(parsed.getTime())) {
      return true;
    }
    if (disabledWeeks.indexOf(parsed.getDay()) !== -1) {
      return true;
    }
    return disabledDates.indexOf(selectedDateLabel(isoDate)) !== -1;
  }

  function renderSlots(slots) {
    slotsBox.innerHTML = '';
    if (!slots.length) {
      slotsBox.textContent = 'No open slots on this date. Please choose another day.';
      timeInput.value = '';
      setSubmitEnabled();
      return;
    }
    slots.forEach(function (slot) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'slot';
      button.textContent = slot.display;
      if (timeInput.value === slot.start_24) {
        button.classList.add('selected');
      }
      button.addEventListener('click', function () {
        timeInput.value = slot.start_24;
        document.querySelectorAll('.slot').forEach(function (el) { el.classList.remove('selected'); });
        button.classList.add('selected');
        setSubmitEnabled();
      });
      slotsBox.appendChild(button);
    });
    setSubmitEnabled();
  }

  function loadSlots() {
    if (!dateInput.value) {
      return;
    }
    slotsBox.textContent = 'Loading open slots...';
    fetch(slotsUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify({ date: dateInput.value })
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.success) {
          slotsBox.textContent = data.message || 'Unable to load open slots.';
          timeInput.value = '';
          setSubmitEnabled();
          return;
        }
        renderSlots(data.slots || []);
      })
      .catch(function () {
        slotsBox.textContent = 'Unable to load open slots. Please try again.';
        timeInput.value = '';
        setSubmitEnabled();
      });
  }

  dateInput.addEventListener('change', function () {
    timeInput.value = '';
    if (isDateClosed(dateInput.value)) {
      slotsBox.textContent = 'This date is not available. Please choose a Monday–Thursday from tomorrow onwards.';
      setSubmitEnabled();
      return;
    }
    loadSlots();
  });

  fetch(availabilityUrl, {
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin'
  }).then(function (response) { return response.json(); }).then(function (data) {
    disabledWeeks = mergeClosedWeekdays(data.weeks || []);
    disabledDates = data.disabled_dates || [];
    if (dateInput.value && isDateClosed(dateInput.value)) {
      slotsBox.textContent = 'This date is not available. Please choose a Monday–Thursday from tomorrow onwards.';
      timeInput.value = '';
      setSubmitEnabled();
    }
  }).catch(function () {});

  if (dateInput.value) {
    loadSlots();
  }
  setSubmitEnabled();
})();
</script>
@endsection
