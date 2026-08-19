<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointment Cancellation - Bansal Immigration</title>
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
            <p style="margin:0 0 10px 0; font-size:14px; line-height:1.7; color:#333;">
              We regret to inform you that your upcoming appointment with <strong>Bansal Immigration</strong> has been <strong>cancelled</strong>. We sincerely apologise for any inconvenience this may cause.
            </p>
            <p style="margin:0; font-size:14px; line-height:1.7; color:#333;">
              Please find the details of the cancelled appointment below.
            </p>
          </td>
        </tr>

        {{-- Cancelled status --}}
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fdf2f2; border:1px solid #f5c6c2; border-radius:8px;">
              <tr>
                <td align="center" style="padding:16px;">
                  <p style="margin:0 0 6px 0; font-size:11px; letter-spacing:0.8px; text-transform:uppercase; color:#888;">Appointment Status</p>
                  <p style="margin:0; font-size:20px; font-weight:900; letter-spacing:1px; color:#e74c3c;">CANCELLED</p>
                  <p style="margin:10px 0 0 0; font-size:14px; font-weight:bold; color:#1c2a3a;">{{ $appointmentDate }} &nbsp;|&nbsp; {{ $appointmentTime }}</p>
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

        {{-- Reason --}}
        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#fff8e1; border:1px solid #f5d48a; border-radius:8px;">
              <tr>
                <td style="padding:14px 16px;">
                  <p style="margin:0 0 8px 0; font-size:14px; font-weight:bold; color:#c47d0e;">Reason for Cancellation</p>
                  <p style="margin:0; font-size:13px; line-height:1.7; color:#5a3e00;">
                    @if($cancellationReason)
                      {{ $cancellationReason }}
                    @else
                      No additional reason was provided for this cancellation.
                    @endif
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Reschedule / Call Us (existing mailto + call-bridge) --}}
        <tr>
          <td align="center" style="padding:0 24px 8px 24px;">
            <p style="margin:0 0 6px 0; font-size:15px; font-weight:bold; color:#1c2a3a;">Would You Like to Reschedule?</p>
            <p style="margin:0 0 16px 0; font-size:13px; line-height:1.6; color:#5a7080;">We'd love to assist you at a time that suits you best. Please reach out to book a new appointment.</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
              <tr>
                <td align="center" style="padding:4px;">
                  <a href="{{ $rescheduleMailtoHref }}" style="display:inline-block; padding:11px 16px; border:2px solid #f5a623; border-radius:8px; color:#d48b0a; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; text-decoration:none;">&#128197; Request to Reschedule</a>
                </td>
                <td align="center" style="padding:4px;">
                  <a href="{{ $callUsHref }}" target="_blank" rel="noopener noreferrer" style="display:inline-block; padding:11px 16px; border:2px solid #1c2a3a; border-radius:8px; background:#1c2a3a; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; text-decoration:none;">&#128222; Call Us</a>
                </td>
              </tr>
            </table>
            <p style="margin:12px 0 0 0; font-size:12px; color:#9aa6b2; font-style:italic;">
              Clicking &quot;Request to Reschedule&quot; will open a pre-filled email. Alternatively, call us on
              <a href="{{ $callUsHref }}" target="_blank" rel="noopener noreferrer" style="color:#1c2a3a; font-weight:bold; text-decoration:none;">{{ $locationPhone }}</a>.
            </p>
          </td>
        </tr>

        {{-- Contact / support --}}
        <tr>
          <td style="padding:16px 24px;">
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
                  <p style="margin:0 0 10px 0; font-size:14px; font-weight:bold; color:#1c2a3a;">Need Help or Have Questions?</p>
                  <p style="margin:0 0 6px 0; font-size:13px; line-height:1.6; color:#333;">
                    &#128222; <a href="{{ $callUsHref }}" target="_blank" rel="noopener noreferrer" style="color:#1c2a3a; text-decoration:none; font-weight:bold;">{{ $locationPhone }}</a>
                  </p>
                  <p style="margin:0 0 6px 0; font-size:13px; line-height:1.6; color:#333;">
                    &#128231; <a href="mailto:info@bansalimmigration.com.au" style="color:#1c2a3a; text-decoration:none; font-weight:bold;">info@bansalimmigration.com.au</a>
                  </p>
                  <p style="margin:0; font-size:13px; line-height:1.6; color:#333;">
                    &#127760; <a href="https://bansalimmigration.com.au" target="_blank" style="color:#1c2a3a; text-decoration:none; font-weight:bold;">bansalimmigration.com.au</a>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:0 24px 8px 24px;">
            <p style="margin:0 0 8px 0; font-size:10px; color:#9aa6b2; line-height:1.6;">
              Bansal Immigration operates in full compliance with OMARA and the Code of Conduct for Registered Migration Agents.
              Consumer Guide:
              <a href="https://www.mara.gov.au/get-help-visa-subsite/Files/consumer_guide_english.pdf" target="_blank" style="color:#7a8a98;">English</a>.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 24px 24px 24px;">
            <p style="margin:0 0 6px 0; font-size:14px; line-height:1.7; color:#333;">We truly value your time and trust in Bansal Immigration. We hope to have the opportunity to assist you soon.</p>
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
