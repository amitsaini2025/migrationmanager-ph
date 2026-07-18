<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Pay for Appointment - Bansal Immigration</title>
  <script src="https://js.stripe.com/v3/"></script>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, sans-serif; background: #f4f4f4; color: #333; }
    .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,.08); overflow: hidden; }
    .header { background: #1c2a3a; color: #fff; padding: 24px; text-align: center; }
    .header h1 { margin: 0 0 6px; font-size: 20px; }
    .header p { margin: 0; font-size: 14px; color: #c8d4df; }
    .body { padding: 24px; }
    .detail { margin-bottom: 10px; font-size: 14px; line-height: 1.5; }
    .detail strong { display: inline-block; min-width: 72px; color: #555; }
    .amount { font-size: 28px; font-weight: bold; color: #1c2a3a; margin: 20px 0 16px; text-align: center; }
    #payment-element { margin: 16px 0; }
    #pay-button { width: 100%; padding: 14px; background: #1c2a3a; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
    #pay-button:disabled { opacity: .6; cursor: not-allowed; }
    .msg { padding: 14px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
    .msg.error { background: #fdecea; color: #b71c1c; border: 1px solid #f5c6cb; }
    .msg.success { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
    .msg.info { background: #eef6fb; color: #1a4a6a; border: 1px solid #bee5eb; }
    #payment-message { margin-top: 12px; font-size: 14px; color: #b71c1c; min-height: 20px; }
    .footer { text-align: center; padding: 16px; font-size: 12px; color: #888; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>Bansal Immigration</h1>
    <p>Appointment Payment</p>
  </div>
  <div class="body">
    @if($error)
      <div class="msg error">{{ $error }}</div>
    @else
      <div class="msg info">Please complete payment to confirm your appointment.</div>
      <div class="detail"><strong>Client:</strong> {{ $appointment->client_name }}</div>
      <div class="detail"><strong>Date:</strong> {{ $appointment->appointment_datetime?->format('l, d F Y') }}</div>
      <div class="detail"><strong>Time:</strong> {{ $appointment->timeslot_full ?? $appointment->appointment_datetime?->format('h:i A') }}</div>
      <div class="detail"><strong>Service:</strong> {{ $appointment->service_type }}</div>
      <div class="amount">${{ number_format($amount, 2) }} AUD</div>

      <form id="payment-form">
        <div id="payment-element"></div>
        <div id="payment-message"></div>
        <button id="pay-button" type="submit">Pay now</button>
      </form>
      <div id="success-panel" class="msg success" style="display:none;"></div>
    @endif
  </div>
  <div class="footer">Secure payment powered by Stripe</div>
</div>

@if(!$error && $stripeKey && $token)
<script>
(function () {
  const stripe = Stripe(@json($stripeKey));
  const token = @json($token);
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const intentUrl = @json(route('public.appointment.pay.intent', ['token' => $token]));
  const completeUrl = @json(route('public.appointment.pay.complete', ['token' => $token]));
  const payButton = document.getElementById('pay-button');
  const paymentMessage = document.getElementById('payment-message');
  const form = document.getElementById('payment-form');
  const successPanel = document.getElementById('success-panel');
  let elements, clientSecret;

  async function initialize() {
    payButton.disabled = true;
    paymentMessage.textContent = 'Loading secure payment form…';
    try {
      const res = await fetch(intentUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({})
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message || 'Unable to load payment form.');
      }
      clientSecret = data.client_secret;
      elements = stripe.elements({ clientSecret });
      const paymentElement = elements.create('payment');
      paymentElement.mount('#payment-element');
      payButton.disabled = false;
      paymentMessage.textContent = '';
    } catch (e) {
      paymentMessage.textContent = e.message || 'Unable to load payment form.';
    }
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    payButton.disabled = true;
    paymentMessage.textContent = 'Processing payment…';

    const { error, paymentIntent } = await stripe.confirmPayment({
      elements,
      redirect: 'if_required',
      confirmParams: {
        receipt_email: @json($appointment->client_email),
      },
    });

    if (error) {
      paymentMessage.textContent = error.message || 'Payment failed.';
      payButton.disabled = false;
      return;
    }

    if (!paymentIntent || paymentIntent.status !== 'succeeded') {
      paymentMessage.textContent = 'Payment was not completed. Please try again.';
      payButton.disabled = false;
      return;
    }

    try {
      const res = await fetch(completeUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ payment_intent_id: paymentIntent.id })
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message || 'Payment recorded failed.');
      }
      form.style.display = 'none';
      successPanel.style.display = 'block';
      successPanel.textContent = data.message || 'Payment successful. Thank you!';
    } catch (err) {
      paymentMessage.textContent = err.message || 'Payment succeeded but confirmation failed. Please contact the office.';
      payButton.disabled = false;
    }
  });

  initialize();
})();
</script>
@endif
</body>
</html>
