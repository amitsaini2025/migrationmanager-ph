<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Please verify your Personal &amp; Visa details</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; color:#1c2a3a; font-family:Arial, Helvetica, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f4;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:8px; overflow:hidden;">

        @include('emails.partials.appointment-branding-header')

        <tr>
          <td style="padding:8px 24px 18px 24px;">
            <p style="margin:0 0 10px 0; font-size:14px; line-height:1.7; color:#333;">Hi {{ $firstName }}, Bansal Immigration Consultants requests you to verify your Personal &amp; Visa details currently recorded on your file.</p>
            <p style="margin:0; font-size:14px; line-height:1.7; color:#333;">Please review and confirm or request any corrections using the secure link below:</p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 24px 16px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e6eaf0; border-radius:8px; overflow:hidden;">
              <tr>
                <td style="background:#1c2a3a; padding:12px 16px;">
                  <p style="margin:0; font-size:15px; font-weight:bold; color:#f5a623;">
                    Secure verification link
                  </p>
                </td>
              </tr>
              <tr>
                <td style="padding:16px; background:#ffffff;">
                  <p style="margin:0 0 14px 0;" align="center">
                    <a href="{{ $verificationUrl }}" target="_blank" rel="noopener" style="display:inline-block; padding:11px 18px; background:#f5a623; color:#1c2a3a; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; text-decoration:none; border-radius:8px;">Open verification form</a>
                  </p>
                  <p style="margin:0; font-size:12px; line-height:1.6; color:#5a7080; word-break:break-all;">
                    <a href="{{ $verificationUrl }}" target="_blank" rel="noopener" style="color:#1c2a3a; font-weight:bold; text-decoration:underline;">{{ $verificationUrl }}</a>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 24px 24px 24px;">
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
