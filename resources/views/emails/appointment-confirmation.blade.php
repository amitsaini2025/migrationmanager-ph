<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointment Confirmation - Bansal Immigration</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; color:#1c2a3a; font-family:Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f4;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:8px; overflow:hidden;">

        @include('emails.partials.appointment-branding-header')

        <tr>
          <td style="padding:8px 24px 18px 24px;">
            <p style="margin:0 0 10px 0; font-size:14px; line-height:1.7; color:#333;">Dear {{ $clientName }},</p>
            <p style="margin:0; font-size:14px; line-height:1.7; color:#333;">
              Thank you for choosing <strong>Bansal Immigration</strong>. This email confirms your upcoming appointment.
            </p>
          </td>
        </tr>

        {{-- Resume (existing feature, restyled as a card) --}}
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff8e1; border:1px solid #f5d48a; border-radius:8px;">
              <tr>
                <td style="padding:14px 16px;">
                  <p style="margin:0 0 8px 0; font-size:10px; font-weight:bold; color:#ffffff; background:#f5a623; display:inline-block; padding:3px 8px; border-radius:12px; letter-spacing:0.4px;">FIRST-TIME CLIENTS ONLY</p>
                  <p style="margin:0 0 8px 0; font-size:14px; font-weight:bold; color:#1c2a3a;">Please submit your resume prior to your appointment</p>
                  <p style="margin:0 0 8px 0; font-size:12px; font-style:italic; color:#7a5c00;">Returning clients may disregard this section.</p>
                  <p style="margin:0; font-size:13px; line-height:1.6; color:#333;">
                    Email your up-to-date resume/CV to
                    <a href="{{ $resumeMailtoHref }}" style="color:#c47d0e; font-weight:bold; text-decoration:none;">info@bansalimmigration.com.au</a>
                    with the subject <em>&quot;Resume – [Your Full Name] – {{ $resumeDateForSubject }} Appointment&quot;</em>
                    at least <strong>48 hours before</strong> your appointment.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Appointment Details card --}}
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e6eaf0; border-radius:8px; overflow:hidden;">
              <tr>
                <td style="background:#1c2a3a; padding:12px 16px;">
                  <p style="margin:0; font-size:15px; font-weight:bold; color:#f5a623;">
                    &#128197; Appointment Details
                  </p>
                </td>
              </tr>
              <tr>
                <td style="padding:0; background:#ffffff;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td width="28" valign="top" style="padding:12px 0 12px 14px; color:#1c2a3a; font-size:14px;">&#128197;</td>
                      <td style="padding:12px 14px 12px 6px; border-bottom:1px solid #eee; font-size:14px; color:#333;">
                        <strong style="color:#555;">Date:</strong> {{ $appointmentDate }}
                      </td>
                    </tr>
                    <tr>
                      <td width="28" valign="top" style="padding:12px 0 12px 14px; color:#1c2a3a; font-size:14px;">&#128336;</td>
                      <td style="padding:12px 14px 12px 6px; border-bottom:1px solid #eee; font-size:14px; color:#333;">
                        <strong style="color:#555;">Time:</strong> {{ $appointmentTime }}
                      </td>
                    </tr>
                    <tr>
                      <td width="28" valign="top" style="padding:12px 0 12px 14px; color:#1c2a3a; font-size:14px;">&#128196;</td>
                      <td style="padding:12px 14px 12px 6px; border-bottom:1px solid #eee; font-size:14px; color:#333;">
                        <strong style="color:#555;">Service Type:</strong> {{ $serviceType }}
                      </td>
                    </tr>
                    <tr>
                      <td width="28" valign="top" style="padding:12px 0 12px 14px; color:#1c2a3a; font-size:14px;">&#128100;</td>
                      <td style="padding:12px 14px 12px 6px; border-bottom:1px solid #eee; font-size:14px; color:#333;">
                        <strong style="color:#555;">Type:</strong>
                        <span style="display:inline-block; background:#f5a623; color:#1c2a3a; font-size:12px; font-weight:bold; padding:4px 12px; border-radius:20px; margin-left:4px;">{{ $meetingTypeLabel }}</span>
                      </td>
                    </tr>
                    <tr>
                      <td width="28" valign="top" style="padding:12px 0 12px 14px; color:#1c2a3a; font-size:14px;">&#128205;</td>
                      <td style="padding:12px 14px 12px 6px; font-size:14px; color:#333;">
                        <strong style="color:#555;">Location:</strong> {{ $locationAddress }}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        @include('emails.partials.appointment-duration-note')

        @if($adminNotes)
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fef9f0; border:1px solid #ead7f5; border-radius:8px;">
              <tr>
                <td style="padding:14px 16px;">
                  <p style="margin:0 0 6px 0; font-size:14px; font-weight:bold; color:#5b2c83;">Important notes from us</p>
                  <p style="margin:0; font-size:14px; line-height:1.7; color:#333;">{{ $adminNotes }}</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        @endif

        {{-- Type-based reminder card --}}
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef6fb; border-radius:8px;">
              <tr>
                <td valign="top" width="44" style="padding:16px 0 16px 16px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td align="center" valign="middle" width="32" height="32" style="width:32px; height:32px; background:#1c2a3a; border-radius:16px; color:#ffffff; font-size:16px;">&#128339;</td>
                    </tr>
                  </table>
                </td>
                <td style="padding:16px 16px 16px 10px;">
                  <p style="margin:0 0 6px 0; font-size:14px; font-weight:bold; color:#1c2a3a;">{{ $reminderTitle }}</p>
                  <p style="margin:0; font-size:13px; line-height:1.6; color:#1a4a6a;">{{ $reminderBody }}</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- What to bring / have ready --}}
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border:1px solid #e6eaf0; border-radius:8px;">
              <tr>
                <td valign="top" width="44" style="padding:16px 0 16px 16px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td align="center" valign="middle" width="32" height="32" style="width:32px; height:32px; background:#1c2a3a; border-radius:16px; color:#ffffff; font-size:16px;">&#128203;</td>
                    </tr>
                  </table>
                </td>
                <td style="padding:16px 16px 16px 10px;">
                  <p style="margin:0 0 10px 0; font-size:14px; font-weight:bold; color:#1c2a3a;">{{ $bringTitle }}</p>
                  @foreach($bringItems as $bringItem)
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;">
                      <tr>
                        <td width="22" valign="top" style="color:#f5a623; font-size:14px; font-weight:bold; padding-top:1px;">&#10003;</td>
                        <td style="font-size:13px; line-height:1.5; color:#333;">{{ $bringItem }}</td>
                      </tr>
                    </table>
                  @endforeach
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Contact / support --}}
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border:1px solid #e6eaf0; border-radius:8px;">
              <tr>
                <td valign="top" width="44" style="padding:16px 0 16px 16px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td align="center" valign="middle" width="32" height="32" style="width:32px; height:32px; background:#1c2a3a; border-radius:16px; color:#ffffff; font-size:16px;">&#127911;</td>
                    </tr>
                  </table>
                </td>
                <td style="padding:16px 16px 16px 10px;">
                  <p style="margin:0 0 10px 0; font-size:14px; font-weight:bold; color:#1c2a3a;">Need to Reschedule or Have Questions?</p>
                  <p style="margin:0 0 6px 0; font-size:13px; line-height:1.6; color:#333;">
                    &#128222; <a href="tel:{{ $locationPhoneTel }}" style="color:#1c2a3a; text-decoration:none; font-weight:bold;">{{ $locationPhone }}</a>
                  </p>
                  <p style="margin:0 0 6px 0; font-size:13px; line-height:1.6; color:#333;">
                    &#128231; <a href="mailto:info@bansalimmigration.com.au" style="color:#1c2a3a; text-decoration:none; font-weight:bold;">info@bansalimmigration.com.au</a>
                  </p>
                  <p style="margin:0 0 10px 0; font-size:13px; line-height:1.6; color:#333;">
                    &#127760; <a href="https://bansalimmigration.com.au" target="_blank" style="color:#1c2a3a; text-decoration:none; font-weight:bold;">bansalimmigration.com.au</a>
                  </p>
                  <p style="margin:0; font-size:12px; color:#5a7080;">Please provide at least 24 hours' notice if you need to reschedule.</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Existing legal content, compact so the new layout stays primary --}}
        <tr>
          <td style="padding:0 24px 8px 24px;">
            <p style="margin:0 0 8px 0; font-size:10px; color:#9aa6b2; line-height:1.6;">
              By booking this appointment, you acknowledge the following: This appointment is considered confirmed upon receipt of this email.
              Bansal Immigration reserves the right to cancel or reschedule any appointment at any time, with or without prior notice, due to unforeseen operational circumstances.
              Phone and video consultations may be subject to delays or interruptions beyond our control.
              Clients arriving more than 10 minutes late may be required to reschedule. Failure to attend without prior notice may result in forfeiture of the appointment slot.
            </p>
            <p style="margin:0; font-size:10px; color:#9aa6b2; line-height:1.6;">
              Bansal Immigration operates in full compliance with OMARA and the Code of Conduct for Registered Migration Agents.
              Consumer Guide:
              <a href="https://www.mara.gov.au/get-help-visa-subsite/Files/consumer_guide_english.pdf" target="_blank" style="color:#7a8a98;">English</a>.
            </p>
          </td>
        </tr>

        @if($cancelUrl && $rescheduleUrl && $confirmUrl)
        <tr>
          <td align="center" style="padding:18px 16px 8px 16px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
              <tr>
                <td align="center" style="padding:4px;">
                  <a href="{{ $cancelUrl }}" style="display:inline-block; padding:11px 16px; border:2px solid #e74c3c; border-radius:8px; color:#e74c3c; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; text-decoration:none;">&#10005; Cancel</a>
                </td>
                <td align="center" style="padding:4px;">
                  <a href="{{ $rescheduleUrl }}" style="display:inline-block; padding:11px 16px; border:2px solid #f5a623; border-radius:8px; color:#d48b0a; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; text-decoration:none;">&#128197; Reschedule</a>
                </td>
                <td align="center" style="padding:4px;">
                  <a href="{{ $confirmUrl }}" style="display:inline-block; padding:11px 16px; border:2px solid #27ae60; border-radius:8px; background:#27ae60; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; text-decoration:none;">&#10003; Confirm</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        @endif

        <tr>
          <td style="padding:10px 24px 24px 24px;">
            <p style="margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#333;">We look forward to assisting you with your immigration journey.</p>
            <p style="margin:0; font-size:14px; line-height:1.7; color:#333;">Warm regards,</p>
            <p style="margin:6px 0 0 0; font-size:14px; font-weight:bold; color:#1c2a3a;">Bansal Immigration Team</p>
          </td>
        </tr>

        <tr>
          <td style="background:#1c2a3a; padding:16px 20px; text-align:center;">
            <p style="margin:0; font-size:11px; color:#8fa3b3;">&copy; {{ date('Y') }} Bansal Immigration. All rights reserved.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
