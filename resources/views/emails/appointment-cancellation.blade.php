<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointment Cancellation - Bansal Immigration</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f4f4f4; color: #333; font-size: 14px; }
    .wrapper { max-width: 700px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.12); }

    .body { padding: 28px 36px; }
    .greeting p { margin-bottom: 10px; line-height: 1.7; }

    .cancelled-box { background: #fdf2f2; border: 2px dashed #e74c3c; border-radius: 8px; text-align: center; padding: 18px 20px; margin: 20px 0; }
    .cancelled-box .label { color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
    .cancelled-box .icon { font-size: 38px; line-height: 1; }
    .cancelled-box .status-label { font-size: 20px; font-weight: 900; color: #e74c3c; margin-top: 6px; letter-spacing: 1px; }
    .cancelled-box .appt-date { margin-top: 10px; font-size: 15px; font-weight: bold; color: #1c2a3a; }

    .section-title { font-size: 15px; font-weight: bold; color: #1c2a3a; border-bottom: 2px solid #1c2a3a; padding-bottom: 6px; margin: 22px 0 14px; }

    .details-table { width: 100%; border-collapse: collapse; }
    .details-table tr { border-bottom: 1px solid #e8e8e8; }
    .details-table tr:last-child { border-bottom: none; }
    .details-table td { padding: 10px 8px; vertical-align: top; }
    .details-table td:first-child { font-weight: bold; color: #555; width: 160px; }

    .reason-box { background: #fff8e1; border-left: 5px solid #f5a623; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .reason-box h3 { color: #c47d0e; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .reason-box p { line-height: 1.7; color: #5a3e00; }

    .action-section { margin: 28px 0; text-align: center; }
    .action-section .action-title { font-size: 15px; font-weight: bold; color: #1c2a3a; margin-bottom: 6px; }
    .action-section .action-subtitle { font-size: 13px; color: #777; margin-bottom: 20px; }
    .btn { display: inline-block; padding: 13px 26px; border-radius: 6px; font-size: 14px; font-weight: bold; text-decoration: none; cursor: pointer; border: none; letter-spacing: 0.3px; }
    .btn-reschedule { background: #2980b9; color: #fff; }
    .btn-contact { background: #1c2a3a; color: #fff; }
    .btn-icon { margin-right: 6px; }
    .btn-note { font-size: 12px; color: #999; margin-top: 14px; font-style: italic; }

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
        <p style="margin:6px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#c8d4df; line-height:1.4;">Appointment Cancellation</p>
      </td>
    </tr>
  </table>

  <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse; mso-table-lspace:0; mso-table-rspace:0;">
    <tr>
      <td style="padding:12px 20px; background-color:#c0392b;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;">
          <tr>
            <td align="left" style="font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; color:#ffffff; line-height:1.5; letter-spacing:0.3px;">
              <span style="font-size:16px; line-height:1; vertical-align:-2px; margin-right:4px; text-decoration:none;">&#10060;</span>
              <span style="vertical-align:middle;">Appointment Cancellation Notice</span>
              <span style="display:inline-block; vertical-align:middle; margin-left:8px; background-color:#a93226; color:#ffffff; font-size:12px; font-weight:bold; padding:4px 10px; border-radius:12px; letter-spacing:0.2px; mso-padding-alt:4px 10px;">Important</span>
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
        We regret to inform you that your upcoming appointment with <strong>Bansal Immigration</strong> has been <strong>cancelled</strong>. We sincerely apologise for any inconvenience this may cause.
      </p>
      <p>
        Please find the details of the cancelled appointment below.
      </p>
    </div>

    <div class="cancelled-box">
      <div class="label">Appointment Status</div>
      <div class="icon">❌</div>
      <div class="status-label">CANCELLED</div>
      <div class="appt-date">📅 {{ $appointmentDate }} &nbsp;|&nbsp; 🕐 {{ $appointmentTime }}</div>
    </div>

    <div class="section-title">Cancelled Appointment Details</div>
    <table class="details-table" role="presentation">
      <tr><td>Date:</td><td>{{ $appointmentDate }}</td></tr>
      <tr><td>Time:</td><td>{{ $appointmentTime }}</td></tr>
      <tr><td>Location:</td><td>{{ $locationAddress }}</td></tr>
    </table>

    <hr class="divider"/>

    <div class="reason-box">
      <h3>📋 Reason for Cancellation</h3>
      <p>
        @if($cancellationReason)
          {{ $cancellationReason }}
        @else
          No additional reason was provided for this cancellation.
        @endif
      </p>
    </div>

    <hr class="divider"/>

    <div class="action-section">
      <div class="action-title">Would You Like to Reschedule?</div>
      <div class="action-subtitle">We'd love to assist you at a time that suits you best. Please reach out to book a new appointment.</div>
      <table align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 16px;">
        <tr>
          <td style="padding:6px;">
            <a href="{{ $rescheduleMailtoHref }}" class="btn btn-reschedule" style="display:inline-block; padding:13px 26px; border-radius:6px; font-size:14px; font-weight:bold; text-decoration:none; background:#2980b9; color:#fff;"><span class="btn-icon">📅</span> Request to Reschedule</a>
          </td>
          <td style="padding:6px;">
            <a href="{{ $callUsHref }}" target="_blank" rel="noopener noreferrer" class="btn btn-contact" style="display:inline-block; padding:13px 26px; border-radius:6px; font-size:14px; font-weight:bold; text-decoration:none; background:#1c2a3a; color:#fff;"><span class="btn-icon">📞</span> Call Us</a>
          </td>
        </tr>
      </table>
      <p class="btn-note">Clicking &quot;Request to Reschedule&quot; will open a pre-filled email. Alternatively, call us on <a href="{{ $callUsHref }}" target="_blank" rel="noopener noreferrer" style="color:#2980b9; font-weight:bold; text-decoration:none;">{{ $locationPhone }}</a>.</p>
    </div>

    <hr class="divider"/>

    <div class="compliance-box">
      <h3>✅ Our Commitment — Registered Migration Agent Code of Conduct</h3>
      <p>
        Bansal Immigration operates in full compliance with the <strong>Office of the Migration Agents Registration Authority (OMARA)</strong> and strictly adheres to the <strong>Code of Conduct for Registered Migration Agents</strong>. We are dedicated to acting in your best interests with complete transparency and confidentiality.
      </p>
      <p style="margin-top:8px;">
        Review your rights as a consumer:
        <a href="https://www.mara.gov.au/get-help-visa-subsite/Files/consumer_guide_english.pdf" target="_blank">MARA Consumer Guide (English)</a>
      </p>
    </div>

    <div class="contact-box">
      <h3>📞 Need Help or Have Questions?</h3>
      <p>📱 <strong>Phone:</strong> <a href="{{ $callUsHref }}" target="_blank" rel="noopener noreferrer">{{ $locationPhone }}</a></p>
      <p>📧 <strong>Email:</strong> <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a></p>
      <p>🌐 <strong>Website:</strong> <a href="https://bansalimmigration.com.au" target="_blank">bansalimmigration.com.au</a></p>
    </div>

    <div class="closing">
      <p>We truly value your time and trust in Bansal Immigration. We hope to have the opportunity to assist you soon. Please don't hesitate to get in touch if you have any questions.</p>
      <p>Warm regards,</p>
      <p class="signature">Bansal Immigration Team</p>
    </div>

  </div>

  <div class="footer">
    <p class="copy">© {{ date('Y') }} Bansal Immigration. All rights reserved.</p>
  </div>

</div>
</body>
</html>
