@extends('public.appointment-action-layout')

@section('content')
  <div class="msg info">Please tell us why you need to cancel. After you submit, this time slot will be released for other bookings.</div>
  <div class="detail"><strong>Date:</strong> {{ $appointmentDate }}</div>
  <div class="detail"><strong>Time:</strong> {{ $appointmentTime }}</div>
  <div class="detail"><strong>Type:</strong> {{ $meetingTypeLabel }}</div>

  @if($errors->any())
    <div class="errors">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ $submitUrl }}">
    @csrf
    <label for="cancellation_reason">Cancellation reason</label>
    <textarea id="cancellation_reason" name="cancellation_reason" rows="4" required minlength="3" maxlength="1000">{{ old('cancellation_reason') }}</textarea>
    <div class="actions">
      <button class="btn-cancel" type="submit">Cancel appointment</button>
    </div>
  </form>
@endsection
