<table class="appt-details" width="100%" cellpadding="0" cellspacing="0" role="presentation">
  <tr>
    <td class="appt-details-head">Appointment Details</td>
  </tr>
  <tr>
    <td class="appt-details-body">
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
          <td class="appt-details-label">Date:</td>
          <td>{{ $appointmentDate }}</td>
        </tr>
        <tr>
          <td class="appt-details-label">Time:</td>
          <td>{{ $appointmentTime }}</td>
        </tr>
        <tr>
          <td class="appt-details-label">Service Type:</td>
          <td>{{ $serviceType }}</td>
        </tr>
        <tr>
          <td class="appt-details-label">Type:</td>
          <td><span class="appt-type-pill">{{ $meetingTypeLabel }}</span></td>
        </tr>
        <tr>
          <td class="appt-details-label">Location:</td>
          <td>{{ $locationAddress }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
