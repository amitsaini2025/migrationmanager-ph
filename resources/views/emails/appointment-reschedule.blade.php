<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointment Rescheduled - Bansal Immigration</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f4f4f4; color: #333; font-size: 14px; }
    .wrapper { max-width: 700px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.12); }
    .body { padding: 28px 36px; }
    .greeting p { margin-bottom: 10px; line-height: 1.7; }

    .reschedule-box { background: #eef6fb; border: 2px solid #2980b9; border-radius: 8px; text-align: center; padding: 18px 20px; margin: 20px 0; }
    .reschedule-box .label { color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
    .reschedule-box .icon { font-size: 38px; line-height: 1; }
    .reschedule-box .status-label { font-size: 20px; font-weight: 900; color: #2980b9; margin-top: 6px; letter-spacing: 1px; }
    .reschedule-box .appt-date { margin-top: 10px; font-size: 15px; font-weight: bold; color: #1c2a3a; }

    .section-title { font-size: 15px; font-weight: bold; color: #1c2a3a; border-bottom: 2px solid #1c2a3a; padding-bottom: 6px; margin: 22px 0 14px; }

    .details-table { width: 100%; border-collapse: collapse; }
    .details-table tr { border-bottom: 1px solid #e8e8e8; }
    .details-table tr:last-child { border-bottom: none; }
    .details-table td { padding: 10px 8px; vertical-align: top; }
    .details-table td:first-child { font-weight: bold; color: #555; width: 160px; }

    .old-slot { color: #c0392b; text-decoration: line-through; }
    .new-slot { color: #27ae60; font-weight: bold; }

    .info-box { background: #fff8e1; border-left: 5px solid #f5a623; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .info-box h3 { color: #c47d0e; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-box p { line-height: 1.7; color: #5a3e00; }

    .arrival-box { background: #eef6fb; border-left: 5px solid #2980b9; border-radius: 4px; padding: 14px 18px; margin: 20px 0; }
    .arrival-box p { line-height: 1.7; color: #1a4a6a; margin: 0; }

    .compliance-box { background: #f0faf4; border-left: 5px solid #27ae60; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .compliance-box h3 { color: #1e8449; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .compliance-box p { line-height: 1.7; }
    .compliance-box a { color: #1e8449; font-weight: bold; text-decoration: none; }

    .contact-box { background: #eef3f8; border-left: 5px solid #1c2a3a; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .contact-box h3 { color: #1c2a3a; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .contact-box p { margin-bottom: 5px; line-height: 1.6; }
    .contact-box a { color: #2980b9; text-decoration: none; font-weight: bold; }

    .closing { margin-top: 22px; line-height: 1.7; }
    .closing p { margin-bottom: 6px; }
    .closing .signature { font-weight: bold; color: #1c2a3a; margin-top: 8px; }

    .footer { background: #1c2a3a; text-align: center; padding: 18px 20px; color: #8fa3b3; font-size: 12px; }
    .footer p { margin-bottom: 5px; }
    .footer a { color: #a8c4d8; text-decoration: none; }
    .footer .copy { margin-top: 10px; color: #5a7080; font-size: 11px; }

    .divider { border: none; border-top: 1px solid #eee; margin: 20px 0; }
  </style>
</head>
<body>
<div class="wrapper">

  {{-- Header --}}
  <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse; mso-table-lspace:0; mso-table-rspace:0; background-color:#1c2a3a;">
    <tr>
      <td align="center" style="padding:28px 20px 24px 20px; background-color:#1c2a3a;">
        <table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center" style="margin:0 auto 12px auto; border-collapse:collapse;">
          <tr>
            <td align="center" style="padding:12px 18px; background-color:#ffffff; border-radius:10px; border:1px solid #dbe4ec;">
              @include('emails.partials.inline-logo')
            </td>
          </tr>
        </table>
        <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:bold; color:#ffffff; line-height:1.3;">Bansal Immigration</p>
        <p style="margin:6px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#c8d4df; line-height:1.4;">Appointment Rescheduled</p>
      </td>
    </tr>
  </table>

  {{-- Banner --}}
  <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;">
    <tr>
      <td style="padding:12px 20px; background-color:#2980b9;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;">
          <tr>
            <td align="left" style="font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:bold; color:#ffffff; line-height:1.5; letter-spacing:0.3px;">
              <span style="font-size:16px; line-height:1; vertical-align:-2px; margin-right:4px;">&#128197;</span>
              <span style="vertical-align:middle;">Your Appointment Has Been Rescheduled</span>
              <span style="display:inline-block; vertical-align:middle; margin-left:8px; background-color:#1a6a9a; color:#ffffff; font-size:12px; font-weight:bold; padding:4px 10px; border-radius:12px; letter-spacing:0.2px;">Updated</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="body">

    <div class="greeting">
      <p>Dear {{ $clientName }},</p>
      <p>
        We would like to inform you that your appointment with <strong>Bansal Immigration</strong> has been <strong>rescheduled</strong>. Please find the updated details below.
      </p>
    </div>

    {{-- Reschedule highlight box --}}
    <div class="reschedule-box">
      <div class="label">Appointment Status</div>
      <div class="icon">&#128197;</div>
      <div class="status-label">RESCHEDULED</div>
      <div class="appt-date">&#128197; {{ $newDate }} &nbsp;|&nbsp; &#128336; {{ $newTime }}</div>
    </div>

    {{-- Change summary table --}}
    <div class="section-title">Appointment Change Summary</div>
    <table class="details-table" role="presentation">
      <tr>
        <td>Previous Date:</td>
        <td class="old-slot">{{ $oldDate }}</td>
      </tr>
      <tr>
        <td>Previous Time:</td>
        <td class="old-slot">{{ $oldTime }}</td>
      </tr>
      <tr>
        <td>New Date:</td>
        <td class="new-slot">{{ $newDate }}</td>
      </tr>
      <tr>
        <td>New Time:</td>
        <td class="new-slot">{{ $newTime }}</td>
      </tr>
      <tr>
        <td>Location:</td>
        <td>{{ $locationAddress }}</td>
      </tr>
    </table>

    <hr class="divider"/>

    <div class="info-box">
      <h3>&#9888; Please Note</h3>
      <p>
        Please make a note of your new appointment date and time. If you are unable to attend at the rescheduled time, please contact us as soon as possible so we can make alternative arrangements.
      </p>
    </div>

    @if($showInPersonArrival)
    <div class="arrival-box">
      <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;">
        <tr>
          <td style="font-size:20px; width:32px; vertical-align:top; padding-right:8px;">&#127970;</td>
          <td>
            <p style="line-height:1.7; color:#1a4a6a; margin:0;">
              <strong>In-Person Appointment Reminder:</strong> Please aim to arrive <strong>at least 10 minutes before</strong> your scheduled appointment time to allow for check-in.
            </p>
          </td>
        </tr>
      </table>
    </div>
    @endif

    <hr class="divider"/>

    <div class="compliance-box">
      <h3>&#9989; Our Commitment — Registered Migration Agent Code of Conduct</h3>
      <p>
        Bansal Immigration operates in full compliance with the <strong>Office of the Migration Agents Registration Authority (OMARA)</strong> and adheres strictly to the <strong>Code of Conduct for Registered Migration Agents</strong>. We are committed to acting in your best interests with complete transparency and confidentiality.
      </p>
      <p style="margin-top:8px;">
        Review your rights: <a href="https://www.mara.gov.au/get-help-visa-subsite/Files/consumer_guide_english.pdf" target="_blank">MARA Consumer Guide (English)</a>
      </p>
    </div>

    <div class="contact-box">
      <h3>&#128222; Need Help or Have Questions?</h3>
      <p>&#128241; <strong>Phone:</strong> <a href="tel:{{ $locationPhoneTel }}">{{ $locationPhone }}</a></p>
      <p>&#128231; <strong>Email:</strong> <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a></p>
      <p>&#127760; <strong>Website:</strong> <a href="https://bansalimmigration.com.au" target="_blank">bansalimmigration.com.au</a></p>
      <p style="margin-top:10px; color:#4a6070;">
        Please provide at least <strong>24 hours' notice</strong> if you need to make any further changes.
      </p>
    </div>

    <div class="closing">
      <p>We look forward to meeting with you at your rescheduled appointment. Please don't hesitate to reach out if you have any questions.</p>
      <p>Warm regards,</p>
      <p class="signature">Bansal Immigration Team</p>
    </div>

  </div>

  <div class="footer">
    <p class="copy">&copy; {{ date('Y') }} Bansal Immigration. All rights reserved.</p>
  </div>

</div>
</body>
</html>
