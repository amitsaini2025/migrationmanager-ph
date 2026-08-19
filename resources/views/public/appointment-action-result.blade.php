@extends('public.appointment-action-layout')

@section('content')
  <div class="msg {{ $ok ? 'success' : 'error' }}">{{ $message }}</div>
  @if($appointment)
    <div class="detail"><strong>Client:</strong> {{ $appointment->client_name }}</div>
    @include('public.partials.appointment-details-card')
  @endif
@endsection
