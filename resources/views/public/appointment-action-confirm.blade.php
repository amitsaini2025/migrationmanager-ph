@extends('public.appointment-action-layout')

@section('content')
  <div class="msg info">Please confirm that you will attend this appointment.</div>
  <div class="detail"><strong>Date:</strong> {{ $appointmentDate }}</div>
  <div class="detail"><strong>Time:</strong> {{ $appointmentTime }}</div>
  <div class="detail"><strong>Type:</strong> {{ $meetingTypeLabel }}</div>

  <form method="POST" action="{{ $submitUrl }}">
    @csrf
    <div class="actions">
      <button class="btn-confirm" type="submit">Confirm booking</button>
    </div>
  </form>
@endsection
