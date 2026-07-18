<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointment Confirmation - Bansal Immigration</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #f4f4f4; color: #333; font-size: 14px; }
    .wrapper { max-width: 700px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }

    /* Body */
    .body { padding: 28px 36px; }
    .greeting { margin-bottom: 16px; line-height: 1.7; }
    .greeting p { margin-bottom: 8px; }

    /* Resume Request Box */
    .resume-box { background: #fff8e1; border-left: 5px solid #f5a623; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .resume-box h3 { color: #c47d0e; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .resume-box p { margin-bottom: 8px; line-height: 1.6; }
    .resume-box ul { padding-left: 20px; margin-top: 6px; }
    .resume-box ul li { margin-bottom: 5px; line-height: 1.6; }
    .resume-box a { color: #c47d0e; font-weight: bold; text-decoration: none; }
    .resume-box a:hover { text-decoration: underline; }

    /* Appointment Details */
    .section-title { font-size: 15px; font-weight: bold; color: #1c2a3a; border-bottom: 2px solid #1c2a3a; padding-bottom: 6px; margin: 22px 0 14px; }
    .details-table { width: 100%; border-collapse: collapse; }
    .details-table tr { border-bottom: 1px solid #e8e8e8; }
    .details-table tr:last-child { border-bottom: none; }
    .details-table td { padding: 10px 8px; vertical-align: top; }
    .details-table td:first-child { font-weight: bold; color: #555; width: 130px; }

    .admin-box { background: #fef9f0; border-left: 5px solid #9b59b6; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .admin-box h3 { color: #5b2c83; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .admin-box p { line-height: 1.7; color: #333; }

    /* What to Expect */
    .expect-box { background: #eef6fb; border-left: 5px solid #2980b9; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .expect-box h3 { color: #1a6a9a; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .expect-box ul { padding-left: 20px; }
    .expect-box ul li { margin-bottom: 6px; line-height: 1.6; }

    /* Please Bring */
    .bring-box { background: #fff8e1; border-left: 5px solid #f5a623; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .bring-box h3 { color: #c47d0e; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .bring-box ul { padding-left: 20px; }
    .bring-box ul li { margin-bottom: 5px; line-height: 1.6; }

    /* Compliance Box */
    .compliance-box { background: #f0faf4; border-left: 5px solid #27ae60; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .compliance-box h3 { color: #1e8449; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .compliance-box p { line-height: 1.7; }
    .compliance-box a { color: #1e8449; font-weight: bold; text-decoration: none; }
    .compliance-box a:hover { text-decoration: underline; }

    /* Contact Box */
    .contact-box { background: #eef3f8; border-left: 5px solid #1c2a3a; border-radius: 4px; padding: 16px 20px; margin: 20px 0; }
    .contact-box h3 { color: #1c2a3a; font-size: 14px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .contact-box p { margin-bottom: 5px; line-height: 1.6; }
    .contact-box a { color: #2980b9; text-decoration: none; font-weight: bold; }
    .contact-box a:hover { text-decoration: underline; }

    /* Closing */
    .closing { margin-top: 22px; line-height: 1.7; }
    .closing p { margin-bottom: 6px; }
    .closing .signature { font-weight: bold; color: #1c2a3a; margin-top: 8px; }

    /* Arrival Note */
    .arrival-outer { margin: 20px 0; }
    .arrival-box { background: #eef6fb; border-left: 5px solid #2980b9; border-radius: 4px; padding: 14px 18px; }
    .arrival-box p { line-height: 1.7; color: #1a4a6a; margin: 0; }
    .arrival-icon { font-size: 20px; width: 28px; vertical-align: top; }

    /* Terms & Conditions */
    .tnc-box { background: #fafafa; border: 1px solid #ebebeb; border-radius: 4px; padding: 12px 16px; margin: 20px 0; }
    .tnc-box h3 { color: #bbb; font-size: 10px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 1px solid #ebebeb; padding-bottom: 5px; }
    .tnc-box p { font-size: 10px; color: #bbb; line-height: 1.6; }

    /* Footer */
    .footer { background: #1c2a3a; text-align: center; padding: 18px 20px; color: #8fa3b3; font-size: 12px; }
    .footer p { margin-bottom: 5px; }
    .footer a { color: #a8c4d8; text-decoration: none; }
    .footer a:hover { text-decoration: underline; }
    .footer .copy { margin-top: 10px; color: #5a7080; font-size: 11px; }
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
        <p style="margin:6px 0 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#c8d4df; line-height:1.4;">Appointment Confirmation</p>
      </td>
    </tr>
  </table>

  <div class="body">

    <div class="greeting">
      <p>Dear {{ $clientName }},</p>
      <p>
        Thank you for choosing <strong>Bansal Immigration</strong> for your immigration needs. We truly appreciate the trust you have placed in us and are committed to providing you with professional, transparent, and personalised guidance throughout your immigration journey.
      </p>
      <p>
        This email confirms your upcoming appointment and includes an important request to help us prepare for a more productive and tailored consultation.
      </p>
    </div>

    <div class="resume-box">
      <div style="display:inline-block; background:#f5a623; color:#fff; font-size:10px; font-weight:bold; padding:2px 9px; border-radius:20px; letter-spacing:0.8px; margin-bottom:10px; text-transform:uppercase;">
        🔖 First-Time Clients Only
      </div>
      <h3>⚠ Please Submit Your Resume Prior to Your Appointment</h3>
      <p style="color:#7a5c00; font-style:italic; margin-bottom:8px; font-size:13px;">
        Returning clients may disregard this section.
      </p>
      <p>
        To help our consultant prepare tailored advice for your session, please email your <strong>up-to-date resume/CV</strong> to
        <a href="{{ $resumeMailtoHref }}">info@bansalimmigration.com.au</a>
        with the subject: <em>&quot;Resume – [Your Full Name] – {{ $resumeDateForSubject }} Appointment&quot;</em> —
        at least <strong>48 hours before</strong> your appointment. If unavailable beforehand, please bring a printed copy on the day.
      </p>
    </div>

    <div class="section-title">Appointment Details</div>
    <table class="details-table" role="presentation">
      <tr><td>Date:</td><td>{{ $appointmentDate }}</td></tr>
      <tr><td>Time:</td><td>{{ $appointmentTime }}</td></tr>
      <tr><td>Location:</td><td>{{ $locationAddress }}</td></tr>
      <tr><td>Service Type:</td><td>{{ $serviceType }}</td></tr>
    </table>

    @if($adminNotes)
    <div class="admin-box">
      <h3>Important notes from us</h3>
      <p>{{ $adminNotes }}</p>
    </div>
    @endif

    @if($showInPersonArrival)
    <div class="arrival-outer">
      <table width="100%" cellpadding="0" cellspacing="0" class="arrival-box" role="presentation" style="background:#eef6fb; border-left:5px solid #2980b9; border-radius:4px; padding:14px 18px;">
        <tr>
          <td class="arrival-icon" style="font-size:20px; width:32px; vertical-align:top; padding-right:8px;">🏢</td>
          <td>
            <p style="line-height:1.7; color:#1a4a6a; margin:0;">
              <strong>In-Person Appointment Reminder:</strong> If you are attending in person, please aim to arrive <strong>at least 10 minutes before</strong> your scheduled appointment time. This allows time for check-in and ensures your consultation begins promptly.
            </p>
          </td>
        </tr>
      </table>
    </div>
    @endif

    <div class="bring-box">
      <h3>Please bring</h3>
      <ul>
        <li>Valid photo identification (Passport, Driver's License)</li>
        <li>All relevant documents related to your visa inquiry</li>
        <li>Any previous correspondence from immigration authorities</li>
      </ul>
    </div>

    <div class="tnc-box">
      <h3>📄 Appointment Terms &amp; Conditions</h3>
      <p>
        By booking this appointment, you acknowledge the following: This appointment is considered confirmed upon receipt of this email.
        Bansal Immigration reserves the right to cancel or reschedule any appointment at any time, with or without prior notice, due to unforeseen operational circumstances.
        Phone and video consultations may be subject to delays or interruptions beyond our control.
        Clients arriving more than 10 minutes late may be required to reschedule. Failure to attend without prior notice may result in forfeiture of the appointment slot.
        Clients are responsible for ensuring all information and documents provided are accurate and complete; Bansal Immigration accepts no liability for outcomes arising from incorrect or incomplete information.
        All information shared during your consultation is treated in strict confidence in accordance with applicable privacy laws and our obligations as registered migration agents.
      </p>
    </div>

    <div class="compliance-box">
      <h3>✅ Our Commitment — Registered Migration Agent Code of Conduct</h3>
      <p>
        Bansal Immigration operates in full compliance with the <strong>Office of the Migration Agents Registration Authority (OMARA)</strong> and adheres strictly to the <strong>Code of Conduct for Registered Migration Agents</strong>. We are committed to acting in your best interests, maintaining confidentiality, providing honest and accurate advice, and ensuring transparency in all professional dealings.
      </p>
      <p style="margin-top:8px;">
        For your protection as a consumer, we encourage you to review the MARA Consumer Guide:
        <a href="https://www.mara.gov.au/get-help-visa-subsite/Files/consumer_guide_english.pdf" target="_blank">Consumer Guide (English)</a>.
      </p>
    </div>

    <div class="contact-box">
      <h3>📞 Need to Reschedule or Have Questions?</h3>
      <p>📱 <strong>Phone:</strong> <a href="tel:{{ $locationPhoneTel }}">{{ $locationPhone }}</a></p>
      <p>📧 <strong>Email:</strong> <a href="mailto:info@bansalimmigration.com.au">info@bansalimmigration.com.au</a></p>
      <p>🌐 <strong>Website:</strong> <a href="https://bansalimmigration.com.au" target="_blank">bansalimmigration.com.au</a></p>
      <p style="margin-top:10px; color:#4a6070;">
        Please provide at least <strong>24 hours' notice</strong> if you need to reschedule your appointment.
      </p>
    </div>

    <div class="closing">
      <p>
        We look forward to assisting you with your immigration journey. Our team is dedicated to offering you the highest standard of professional support and personalised guidance every step of the way.
      </p>
      <p>Should you have any questions or concerns before your appointment, please do not hesitate to reach out — we're here to help.</p>
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
