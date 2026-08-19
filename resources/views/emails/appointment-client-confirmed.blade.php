<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointment Confirmed - Bansal Immigration</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f4f4f4; color: #333; font-size: 14px; }
    .wrapper { max-width: 700px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .body { padding: 28px 36px; }
    .greeting p { margin-bottom: 10px; line-height: 1.7; }
    .confirmed-box { background: #e8f8ef; border: 2px solid #27ae60; border-radius: 8px; text-align: center; padding: 18px 20px; margin: 20px 0; }
    .confirmed-box .status-label { font-size: 20px; font-weight: 900; color: #1e8449; }
    .details-table { width: 100%; border-collapse: collapse; }
    .details-table tr { border-bottom: 1px solid #e8e8e8; }
    .details-table td { padding: 10px 8px; vertical-align: top; }
    .details-table td:first-child { font-weight: bold; color: #555; width: 130px; }
    .contact-box { background: #eef3f8; border-left: 5px solid #1c2a3a; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .contact-box p { margin-bottom: 5px; line-height: 1.6; }
    .contact-box a { color: #2980b9; text-decoration: none; font-weight: bold; }
    .closing { margin-top: 22px; line-height: 1.7; }
    .closing .signature { font-weight: bold; color: #1c2a3a; margin-top: 8px; }
    .footer { background: #1c2a3a; text-align: center; padding: 18px 20px; color: #8fa3b3; font-size: 12px; }
  </style>
</head>
<body>
<div class="wrapper">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse; background-color:#1c2a3a;">
    <tr>
      <td align="center" style="padding:28px 20px 24px 20px; background-color:#1c2a3a;">
        <table cellpadding="0" cellspacing="0" border="0" role="presentation" align="center" style="margin:0 auto 12px auto; border-collapse:collapse;">
          <tr>
            <td align="center" style="padding:12px 18px; background-color:#ffffff; border-radius:10px; border:1px solid #dbe4ec;">
              @include('emails.partials.inline-logo')
            </td>
          </tr>
        </table>
        <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:bold; color:#ffffff;">Bansal Immigration</p>
        <p style="margin:6px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#c8d4df;">Appointment Confirmed</p>
      </td>
    </tr>
  </table>

  <div class="body">
    <div class="greeting">
      <p>Dear {{ $clientName }},</p>
      <p>Thank you — your appointment with <strong>Bansal Immigration</strong> is confirmed. A copy of this notice has also been sent to our office.</p>
    </div>

    <div class="confirmed-box">
      <div class="status-label">CONFIRMED</div>
      <p style="margin-top:8px; font-weight:bold; color:#1c2a3a;">{{ $appointmentDate }} &middot; {{ $appointmentTime }}</p>
    </div>

    <table class="details-table" role="presentation">
      <tr><td>Date:</td><td>{{ $appointmentDate }}</td></tr>
      <tr><td>Time:</td><td>{{ $appointmentTime }}</td></tr>
      <tr><td>Service Type:</td><td>{{ $serviceType }}</td></tr>
      <tr><td>Type:</td><td>{{ $meetingTypeLabel }}</td></tr>
      <tr><td>Location:</td><td>{{ $locationAddress }}</td></tr>
    </table>

    <div class="contact-box">
      <p><strong>Phone:</strong> <a href="tel:{{ $locationPhoneTel }}">{{ $locationPhone }}</a></p>
      <p><strong>Email:</strong> <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a></p>
    </div>

    <div class="closing">
      <p>We look forward to speaking with you.</p>
      <p>Warm regards,</p>
      <p class="signature">Bansal Immigration Team</p>
    </div>
  </div>

  <div class="footer">
    <p>&copy; {{ date('Y') }} Bansal Immigration. All rights reserved.</p>
  </div>
</div>
</body>
</html>
