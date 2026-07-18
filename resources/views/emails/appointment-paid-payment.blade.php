<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Complete Your Appointment Payment - Bansal Immigration</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f4f4f4; color: #333; font-size: 14px; }
    .wrapper { max-width: 700px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .body { padding: 28px 36px; }
    .greeting { margin-bottom: 16px; line-height: 1.7; }
    .greeting p { margin-bottom: 8px; }
    .section-title { font-size: 15px; font-weight: bold; color: #1c2a3a; border-bottom: 2px solid #1c2a3a; padding-bottom: 6px; margin: 22px 0 14px; }
    .details-table { width: 100%; border-collapse: collapse; }
    .details-table tr { border-bottom: 1px solid #e8e8e8; }
    .details-table tr:last-child { border-bottom: none; }
    .details-table td { padding: 10px 8px; vertical-align: top; }
    .details-table td:first-child { font-weight: bold; color: #555; width: 130px; }
    .payment-box { background: #eef6fb; border-left: 5px solid #2980b9; border-radius: 4px; padding: 20px; margin: 22px 0; text-align: center; }
    .payment-box p { line-height: 1.7; margin-bottom: 14px; }
    .payment-amount { font-size: 26px; font-weight: bold; color: #1c2a3a; margin-bottom: 16px; }
    .pay-button { display: inline-block; background: #1c2a3a; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 15px; }
    .pay-link { display: block; margin-top: 12px; font-size: 12px; color: #555; word-break: break-all; }
    .note-box { background: #fff8e1; border-left: 5px solid #f5a623; border-radius: 4px; padding: 16px 20px; margin: 20px 0; line-height: 1.7; }
    .contact-box { background: #eef3f8; border-left: 5px solid #1c2a3a; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .contact-box p { margin-bottom: 5px; line-height: 1.6; }
    .contact-box a { color: #2980b9; text-decoration: none; font-weight: bold; }
    .footer { background: #1c2a3a; text-align: center; padding: 18px 20px; color: #8fa3b3; font-size: 12px; }
  </style>
</head>
<body>
<div class="wrapper">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse; background-color:#1c2a3a;">
    <tr>
      <td align="center" style="padding:28px 20px 24px 20px;">
        <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:18px; font-weight:bold; color:#ffffff;">Bansal Immigration</p>
        <p style="margin:6px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#c8d4df;">Appointment Payment Required</p>
      </td>
    </tr>
  </table>

  <div class="body">
    <div class="greeting">
      <p>Dear {{ $clientName }},</p>
      <p>
        Thank you for booking an appointment with <strong>Bansal Immigration</strong>. Your appointment has been reserved, and payment is required to confirm it.
      </p>
    </div>

    <div class="section-title">Appointment Details</div>
    <table class="details-table" role="presentation">
      <tr><td>Date:</td><td>{{ $appointmentDate }}</td></tr>
      <tr><td>Time:</td><td>{{ $appointmentTime }}</td></tr>
      <tr><td>Location:</td><td>{{ $locationAddress }}</td></tr>
      <tr><td>Service Type:</td><td>{{ $serviceType }}</td></tr>
    </table>

    <div class="payment-box">
      <p>Please complete your payment to confirm this appointment.</p>
      <div class="payment-amount">${{ $amount }} AUD</div>
      <a href="{{ $paymentUrl }}" class="pay-button" target="_blank" rel="noopener">Pay now securely</a>
      <span class="pay-link">{{ $paymentUrl }}</span>
    </div>

    <div class="note-box">
      After payment is completed, you will receive a separate confirmation email with full appointment details and preparation instructions.
    </div>

    <div class="contact-box">
      <p><strong>Need help?</strong></p>
      <p>Phone: <a href="tel:{{ $locationPhoneTel }}">{{ $locationPhone }}</a></p>
      <p>Email: <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a></p>
    </div>

    <p style="line-height:1.7;">Kind regards,<br><strong>Bansal Immigration Team</strong></p>
  </div>

  <div class="footer">
    <p>Secure payment powered by Stripe</p>
    <p>&copy; {{ date('Y') }} Bansal Immigration. All rights reserved.</p>
  </div>
</div>
</body>
</html>
