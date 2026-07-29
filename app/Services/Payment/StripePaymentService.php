<?php

namespace App\Services\Payment;

use App\Models\BookingAppointment;
use App\Models\AppointmentPayment;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Stripe\StripeClient;

class StripePaymentService
{
    /**
     * Initialize Stripe with API key
     */
    public function __construct()
    {
        // Set Stripe API key from config
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Process payment for an appointment
     * 
     * @param BookingAppointment $appointment
     * @param string $paymentMethodId Payment method ID from Stripe.js
     * @param array $metadata Additional metadata (IP, user agent, etc.)
     * @return array ['success' => bool, 'data' => array, 'message' => string]
     */
    public function processPayment(BookingAppointment $appointment, string $paymentMethodId, array $metadata = []): array
    {
        DB::beginTransaction();
        
        try {
            // Create payment record with pending status
            $payment = AppointmentPayment::create([
                'appointment_id' => $appointment->id,
                'payment_gateway' => 'stripe',
                'payment_method_id' => $paymentMethodId,
                'amount' => $appointment->final_amount ?? $appointment->amount,
                'currency' => 'AUD',
                'status' => 'pending',
                'client_ip' => $metadata['ip'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
            ]);

            // Get or create Stripe customer
            $customer = $this->getOrCreateCustomer($appointment);
            
            // Update payment with customer ID
            $payment->update(['customer_id' => $customer->id]);

            // Create PaymentIntent
            $paymentIntent = $this->createPaymentIntent(
                $appointment,
                $customer->id,
                $paymentMethodId,
                $payment->id
            );

            // Update payment record with Stripe data
            $payment->update([
                'transaction_id' => $paymentIntent->id,
                'charge_id' => $paymentIntent->latest_charge ?? null,
                'status' => $this->mapStripeStatus($paymentIntent->status),
                'transaction_data' => $paymentIntent->toArray(),
                'receipt_url' => $paymentIntent->charges->data[0]->receipt_url ?? null,
                'processed_at' => now(),
            ]);

            // If payment succeeded, update appointment
            if ($paymentIntent->status === 'succeeded') {
                $this->updateAppointmentAfterPayment($appointment, $payment);
                
                DB::commit();
                
                Log::info('Stripe payment succeeded', [
                    'appointment_id' => $appointment->id,
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $payment->amount,
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'payment_id' => $payment->id,
                        'appointment_id' => $appointment->id,
                        'payment_intent_id' => $paymentIntent->id,
                        'charge_id' => $paymentIntent->latest_charge,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => 'succeeded',
                        'receipt_url' => $payment->receipt_url,
                        'paid_at' => $appointment->paid_at->toIso8601String(),
                    ],
                    'message' => 'Payment processed successfully',
                ];
            }

            // Payment requires additional action (e.g., 3D Secure)
            if ($paymentIntent->status === 'requires_action') {
                DB::commit();
                
                return [
                    'success' => false,
                    'data' => [
                        'payment_id' => $payment->id,
                        'payment_intent_id' => $paymentIntent->id,
                        'requires_action' => true,
                        'client_secret' => $paymentIntent->client_secret,
                        'next_action' => $paymentIntent->next_action,
                    ],
                    'message' => 'Payment requires additional authentication',
                ];
            }

            // Payment failed or in other status
            DB::commit();
            
            return [
                'success' => false,
                'data' => [
                    'payment_id' => $payment->id,
                    'status' => $paymentIntent->status,
                ],
                'message' => 'Payment could not be completed. Status: ' . $paymentIntent->status,
            ];

        } catch (CardException $e) {
            DB::rollBack();
            
            // Card was declined
            $error = $e->getError();
            $errorMessage = $error->message ?? 'Card was declined';
            
            $failedPaymentId = $this->recordFailedPayment(
                $appointment,
                $paymentMethodId,
                $errorMessage,
                $metadata,
                $payment ?? null
            );
            
            Log::warning('Stripe card declined', [
                'appointment_id' => $appointment->id,
                'error' => $errorMessage,
                'code' => $error->code ?? null,
            ]);

            return [
                'success' => false,
                'data' => ['payment_id' => $failedPaymentId],
                'message' => $errorMessage,
            ];

        } catch (RateLimitException $e) {
            DB::rollBack();
            
            Log::error('Stripe rate limit exceeded', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Too many payment requests. Please try again later.',
            ];

        } catch (InvalidRequestException $e) {
            DB::rollBack();
            
            $errorMessage = $e->getMessage();
            
            $failedPaymentId = $this->recordFailedPayment(
                $appointment,
                $paymentMethodId,
                $errorMessage,
                $metadata,
                $payment ?? null
            );
            
            Log::error('Stripe invalid request', [
                'appointment_id' => $appointment->id,
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'data' => ['payment_id' => $failedPaymentId],
                'message' => $errorMessage,
            ];

        } catch (AuthenticationException $e) {
            DB::rollBack();
            
            Log::error('Stripe authentication failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Payment system authentication error. Please contact support.',
            ];

        } catch (ApiConnectionException $e) {
            DB::rollBack();
            
            Log::error('Stripe API connection failed', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Unable to connect to payment system. Please try again.',
            ];

        } catch (ApiErrorException $e) {
            DB::rollBack();
            
            $errorMessage = $e->getMessage();
            
            $failedPaymentId = $this->recordFailedPayment(
                $appointment,
                $paymentMethodId,
                $errorMessage,
                $metadata,
                $payment ?? null
            );
            
            Log::error('Stripe API error', [
                'appointment_id' => $appointment->id,
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'data' => ['payment_id' => $failedPaymentId],
                'message' => $errorMessage,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            $failedPaymentId = $this->recordFailedPayment(
                $appointment,
                $paymentMethodId,
                $e->getMessage(),
                $metadata,
                $payment ?? null
            );
            
            Log::error('Unexpected payment error', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => ['payment_id' => $failedPaymentId],
                'message' => 'An unexpected error occurred. Please try again.',
            ];
        }
    }

    /**
     * Persist a failed payment attempt after the processing transaction was rolled back.
     *
     * The pending row created inside the transaction no longer exists once rollBack()
     * runs, so the failure has to be inserted as its own statement to keep the audit
     * trail. Never throws: a bookkeeping failure must not replace the Stripe error.
     *
     * @param BookingAppointment $appointment
     * @param string $paymentMethodId
     * @param string $errorMessage
     * @param array $metadata
     * @param AppointmentPayment|null $rolledBackPayment Discarded pending row, used for Stripe ids already obtained
     * @return int|null Id of the stored failed payment record
     */
    private function recordFailedPayment(
        BookingAppointment $appointment,
        string $paymentMethodId,
        string $errorMessage,
        array $metadata = [],
        ?AppointmentPayment $rolledBackPayment = null
    ): ?int {
        try {
            $failedPayment = AppointmentPayment::create([
                'appointment_id' => $appointment->id,
                'payment_gateway' => 'stripe',
                'transaction_id' => $rolledBackPayment->transaction_id ?? null,
                'charge_id' => $rolledBackPayment->charge_id ?? null,
                'customer_id' => $rolledBackPayment->customer_id ?? null,
                'payment_method_id' => $paymentMethodId,
                'amount' => $rolledBackPayment->amount ?? ($appointment->final_amount ?? $appointment->amount),
                'currency' => $rolledBackPayment->currency ?? 'AUD',
                'status' => 'failed',
                'error_message' => $errorMessage,
                'client_ip' => $metadata['ip'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'processed_at' => now(),
            ]);

            return $failedPayment->id;
        } catch (Exception $e) {
            Log::error('Could not store failed payment record', [
                'appointment_id' => $appointment->id,
                'payment_error' => $errorMessage,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get or create Stripe customer
     * 
     * @param BookingAppointment $appointment
     * @return Customer
     */
    private function getOrCreateCustomer(BookingAppointment $appointment): Customer
    {
        // Check if customer already exists by email
        $existingPayment = AppointmentPayment::where('appointment_id', $appointment->id)
            ->whereNotNull('customer_id')
            ->first();

        if ($existingPayment && $existingPayment->customer_id) {
            try {
                return Customer::retrieve($existingPayment->customer_id);
            } catch (Exception $e) {
                // Customer not found, create new one
                Log::warning('Stripe customer not found, creating new', [
                    'customer_id' => $existingPayment->customer_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Create new customer
        return Customer::create([
            'email' => $appointment->client_email,
            'name' => $appointment->client_name,
            'phone' => $appointment->client_phone,
            'metadata' => [
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
            ],
        ]);
    }

    /**
     * Create PaymentIntent
     * 
     * @param BookingAppointment $appointment
     * @param string $customerId
     * @param string $paymentMethodId
     * @param int $paymentRecordId
     * @return PaymentIntent
     */
    private function createPaymentIntent(
        BookingAppointment $appointment,
        string $customerId,
        string $paymentMethodId,
        int $paymentRecordId
    ): PaymentIntent {
        $amount = $appointment->final_amount ?? $appointment->amount;
        
        // Convert amount to cents (Stripe requires amount in smallest currency unit).
        // Round instead of truncating so float representation (e.g. 19.99 * 100) cannot
        // undercharge by a cent and fail the amount checks used by the recording paths.
        $amountInCents = (int) round($amount * 100);

        return PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => 'aud',
            'customer' => $customerId,
            'payment_method' => $paymentMethodId,
            'confirm' => true, // Automatically confirm the payment
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never', // Disable redirects for API payments
            ],
            'description' => "Payment for appointment #{$appointment->id} - {$appointment->service_type}",
            'metadata' => [
                'appointment_id' => $appointment->id,
                'payment_record_id' => $paymentRecordId,
                'client_id' => $appointment->client_id,
                'client_email' => $appointment->client_email,
                'service_type' => $appointment->service_type ?? 'consultation',
            ],
            'receipt_email' => $appointment->client_email,
        ]);
    }

    /**
     * Update appointment after successful payment
     * 
     * @param BookingAppointment $appointment
     * @param AppointmentPayment $payment
     * @return void
     */
    private function updateAppointmentAfterPayment(BookingAppointment $appointment, AppointmentPayment $payment): void
    {
        $appointment->update([
            'status' => 'paid',
            'is_paid' => true,
            'payment_status' => 'completed',
            'payment_method' => 'stripe',
            'paid_at' => now(),
        ]);

        Log::info('Appointment updated after payment', [
            'appointment_id' => $appointment->id,
            'status' => 'paid',
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Map Stripe payment status to our internal status
     * 
     * @param string $stripeStatus
     * @return string
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match($stripeStatus) {
            'succeeded' => 'succeeded',
            'processing' => 'processing',
            'requires_payment_method', 'requires_confirmation', 'requires_action' => 'pending',
            'canceled', 'failed' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Create a PaymentIntent for the public appointment pay-by-link page (AUD).
     *
     * @return array{success: bool, data: array, message: string}
     */
    public function createPublicPaymentIntent(BookingAppointment $appointment): array
    {
        try {
            if (! config('services.stripe.secret')) {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Payment system is not configured. Please contact the office.',
                ];
            }

            $amount = (float) ($appointment->final_amount ?? $appointment->amount);
            if ($amount <= 0) {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Invalid appointment amount.',
                ];
            }

            $amountInCents = (int) round($amount * 100);

            // Match Bansal website: StripeClient + minimal PaymentIntent payload.
            $client = new StripeClient([
                'api_key' => config('services.stripe.secret'),
            ]);

            $paymentIntent = $client->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => 'aud',
                'metadata' => [
                    'appointment_id' => (string) $appointment->id,
                    'payment_token' => (string) ($appointment->payment_token ?? ''),
                ],
                'description' => sprintf(
                    'Immigration Consultation - %s for %s',
                    $appointment->service_type ?? 'consultation',
                    $appointment->client_name ?? 'Client'
                ),
            ]);

            return [
                'success' => true,
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $amount,
                    'currency' => 'AUD',
                ],
                'message' => 'Payment intent created.',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to create public appointment PaymentIntent', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Unable to start payment. Please try again or contact the office.',
            ];
        } catch (Exception $e) {
            Log::error('Failed to create public appointment PaymentIntent', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Unable to start payment. Please try again or contact the office.',
            ];
        }
    }

    /**
     * Record payment by PaymentIntent ID (Option C: frontend owns PaymentIntent, backend only records).
     * Call this after the frontend has created and confirmed the PaymentIntent with Stripe.
     *
     * @param BookingAppointment $appointment
     * @param string $paymentIntentId Stripe PaymentIntent ID (pi_...)
     * @param array $metadata Optional (ip, user_agent)
     * @return array ['success' => bool, 'data' => array, 'message' => string]
     */
    public function recordPaymentByIntent(BookingAppointment $appointment, string $paymentIntentId, array $metadata = []): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Payment has not succeeded. Current status: ' . $paymentIntent->status,
                ];
            }

            $appointmentAmount = (float) ($appointment->final_amount ?? $appointment->amount);
            $amountInCents = (int) round($appointmentAmount * 100);
            if ($paymentIntent->amount !== $amountInCents) {
                Log::warning('Record payment: amount mismatch', [
                    'appointment_id' => $appointment->id,
                    'expected_cents' => $amountInCents,
                    'stripe_cents' => $paymentIntent->amount,
                ]);
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Payment amount does not match appointment amount.',
                ];
            }

            // Bind the intent to this appointment. Intents created by the CRM carry
            // appointment_id, and pay-by-link intents also carry payment_token, so any
            // metadata naming a different appointment is rejected outright.
            $intentAppointmentId = $paymentIntent->metadata->appointment_id ?? null;
            $intentPaymentToken = $paymentIntent->metadata->payment_token ?? null;

            if (!empty($intentAppointmentId) && (string) $intentAppointmentId !== (string) $appointment->id) {
                Log::warning('Record payment: intent bound to another appointment', [
                    'appointment_id' => $appointment->id,
                    'intent_appointment_id' => (string) $intentAppointmentId,
                    'payment_intent_id' => $paymentIntent->id,
                ]);

                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'PaymentIntent does not belong to this appointment.',
                ];
            }

            if (!empty($intentPaymentToken) && !empty($appointment->payment_token)
                && (string) $intentPaymentToken !== (string) $appointment->payment_token) {
                Log::warning('Record payment: intent payment token mismatch', [
                    'appointment_id' => $appointment->id,
                    'payment_intent_id' => $paymentIntent->id,
                ]);

                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'PaymentIntent does not belong to this appointment.',
                ];
            }

            $appointmentCurrency = 'aud';
            $intentCurrency = strtolower((string) ($paymentIntent->currency ?? ''));
            $currencyMismatch = $intentCurrency !== '' && $intentCurrency !== $appointmentCurrency;
            $unbound = empty($intentAppointmentId) && empty($intentPaymentToken);
            $enforceBinding = (bool) config('services.stripe.enforce_appointment_intent_binding', true);

            // A payment that was already in flight when enforcement was switched on is
            // still recorded rather than lost.
            if ($enforceBinding && $this->intentPredatesBindingCutover($paymentIntent)) {
                $enforceBinding = false;
            }

            if ($unbound || $currencyMismatch) {
                Log::warning('Record payment: weakly bound PaymentIntent', [
                    'appointment_id' => $appointment->id,
                    'payment_intent_id' => $paymentIntent->id,
                    'unbound' => $unbound,
                    'intent_currency' => $intentCurrency,
                    'enforced' => $enforceBinding,
                ]);

                if ($enforceBinding) {
                    Log::error('Record payment rejected: PaymentIntent is not bound to this appointment', [
                        'appointment_id' => $appointment->id,
                        'payment_intent_id' => $paymentIntent->id,
                        'intent_currency' => $intentCurrency,
                    ]);

                    return [
                        'success' => false,
                        'data' => [],
                        'message' => 'Payment could not be matched to this appointment. Please contact the office.',
                    ];
                }
            }

            // Stamp the appointment onto an unbound intent so the same payment cannot be
            // presented later for a different appointment. The retrieved intent stays the
            // source of truth for the fields recorded below.
            if ($unbound) {
                $this->claimPaymentIntentForAppointment($paymentIntent, $appointment);
            }

            // Avoid duplicate record for same PaymentIntent. Failed attempts are skipped so a
            // stored failure for this intent still allows the successful payment to be recorded.
            $existing = AppointmentPayment::where('transaction_id', $paymentIntent->id)
                ->where('status', '!=', 'failed')
                ->first();
            if ($existing) {
                if ($existing->appointment_id !== (int) $appointment->id) {
                    return [
                        'success' => false,
                        'data' => ['payment_id' => $existing->id],
                        'message' => 'This payment was already recorded for another appointment.',
                    ];
                }
                $this->updateAppointmentAfterPayment($appointment, $existing);
                $appointment->refresh();
                return [
                    'success' => true,
                    'data' => [
                        'payment_id' => $existing->id,
                        'appointment_id' => $appointment->id,
                        'payment_intent_id' => $paymentIntent->id,
                        'charge_id' => $paymentIntent->latest_charge,
                        'amount' => $existing->amount,
                        'currency' => $existing->currency,
                        'status' => 'succeeded',
                        'receipt_url' => $existing->receipt_url,
                        'paid_at' => $appointment->paid_at ? $appointment->paid_at->toIso8601String() : null,
                    ],
                    'message' => 'Payment already recorded.',
                ];
            }

            $receiptUrl = null;
            if (!empty($paymentIntent->charges->data[0]->receipt_url)) {
                $receiptUrl = $paymentIntent->charges->data[0]->receipt_url;
            }

            $payment = AppointmentPayment::create([
                'appointment_id' => $appointment->id,
                'payment_gateway' => 'stripe',
                'transaction_id' => $paymentIntent->id,
                'charge_id' => $paymentIntent->latest_charge,
                'customer_id' => $paymentIntent->customer ?? null,
                'payment_method_id' => is_string($paymentIntent->payment_method) ? $paymentIntent->payment_method : ($paymentIntent->payment_method->id ?? null),
                'amount' => $appointmentAmount,
                'currency' => strtoupper($paymentIntent->currency ?? 'AUD'),
                'status' => 'succeeded',
                'transaction_data' => $paymentIntent->toArray(),
                'receipt_url' => $receiptUrl,
                'client_ip' => $metadata['ip'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'processed_at' => now(),
            ]);

            $this->updateAppointmentAfterPayment($appointment, $payment);

            Log::info('Stripe payment recorded by intent', [
                'appointment_id' => $appointment->id,
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent->id,
            ]);

            $appointment->refresh();

            return [
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'appointment_id' => $appointment->id,
                    'payment_intent_id' => $paymentIntent->id,
                    'charge_id' => $paymentIntent->latest_charge,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => 'succeeded',
                    'receipt_url' => $payment->receipt_url,
                    'paid_at' => $appointment->paid_at ? $appointment->paid_at->toIso8601String() : null,
                ],
                'message' => 'Payment processed successfully',
            ];
        } catch (InvalidRequestException $e) {
            Log::error('Stripe record payment: invalid request', [
                'appointment_id' => $appointment->id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'data' => [],
                'message' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            Log::error('Stripe record payment error', [
                'appointment_id' => $appointment->id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'data' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Whether an intent was created before binding enforcement was switched on.
     *
     * @param PaymentIntent $paymentIntent
     * @return bool
     */
    private function intentPredatesBindingCutover(PaymentIntent $paymentIntent): bool
    {
        $cutover = config('services.stripe.intent_binding_cutover');

        if (empty($cutover) || empty($paymentIntent->created)) {
            return false;
        }

        $cutoverTimestamp = strtotime((string) $cutover);

        if ($cutoverTimestamp === false) {
            Log::warning('Ignoring unreadable stripe.intent_binding_cutover value', [
                'value' => (string) $cutover,
            ]);

            return false;
        }

        return (int) $paymentIntent->created < $cutoverTimestamp;
    }

    /**
     * Write the appointment binding onto a PaymentIntent that was created without one.
     *
     * Never throws: failing to stamp Stripe metadata must not stop a verified payment
     * from being recorded.
     *
     * @param PaymentIntent $paymentIntent
     * @param BookingAppointment $appointment
     * @return void
     */
    private function claimPaymentIntentForAppointment(PaymentIntent $paymentIntent, BookingAppointment $appointment): void
    {
        try {
            PaymentIntent::update($paymentIntent->id, [
                'metadata' => [
                    'appointment_id' => (string) $appointment->id,
                    'claimed_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (Exception $e) {
            Log::warning('Could not claim PaymentIntent for appointment', [
                'appointment_id' => $appointment->id,
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify a PaymentIntent settled an invoice, without touching appointment tables.
     *
     * Returns ['verified' => bool, 'reason' => string|null, 'data' => array].
     * `verified` is false whenever Stripe cannot confirm the payment — the caller
     * decides whether that is fatal (see services.stripe.enforce_portal_payment_verification).
     *
     * @param string $paymentIntentId Stripe PaymentIntent ID (pi_...)
     * @param float  $expectedAmount  Amount the invoice expects, in dollars
     * @param array  $expectedMetadata Key/value pairs that must match intent metadata when present
     */
    public function verifyPaymentIntentForInvoice(string $paymentIntentId, float $expectedAmount, array $expectedMetadata = []): array
    {
        if (! preg_match('/^pi_[A-Za-z0-9_]+$/', $paymentIntentId)) {
            return [
                'verified' => false,
                'reason' => 'Payment token is not a Stripe PaymentIntent id.',
                'data' => [],
            ];
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            Log::warning('Invoice payment verification: Stripe lookup failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'verified' => false,
                'reason' => 'PaymentIntent could not be retrieved from Stripe.',
                'data' => [],
            ];
        }

        if ($paymentIntent->status !== 'succeeded') {
            return [
                'verified' => false,
                'reason' => 'Payment has not succeeded. Current status: ' . $paymentIntent->status,
                'data' => ['status' => $paymentIntent->status],
            ];
        }

        // Stripe amounts are in cents; allow a 1 cent tolerance for rounding.
        $expectedCents = (int) round($expectedAmount * 100);
        if ((int) $paymentIntent->amount + 1 < $expectedCents) {
            return [
                'verified' => false,
                'reason' => 'Payment amount is less than the invoice amount.',
                'data' => [
                    'expected_cents' => $expectedCents,
                    'stripe_cents' => (int) $paymentIntent->amount,
                ],
            ];
        }

        foreach ($expectedMetadata as $key => $value) {
            $actual = $paymentIntent->metadata->{$key} ?? null;
            if ($actual !== null && (string) $actual !== (string) $value) {
                return [
                    'verified' => false,
                    'reason' => 'PaymentIntent does not belong to this invoice.',
                    'data' => ['metadata_key' => $key],
                ];
            }
        }

        return [
            'verified' => true,
            'reason' => null,
            'data' => [
                'payment_intent_id' => $paymentIntent->id,
                'amount_cents' => (int) $paymentIntent->amount,
                'currency' => strtoupper($paymentIntent->currency ?? 'AUD'),
                'charge_id' => $paymentIntent->latest_charge ?? null,
            ],
        ];
    }

    /**
     * Create a PaymentIntent for a client portal invoice. The amount is supplied by
     * the server from invoice data, never by the caller.
     *
     * @param array $metadata Stamped onto the intent so it can be bound back to the invoice
     */
    public function createInvoicePaymentIntent(float $amount, array $metadata = [], string $currency = 'aud'): array
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($amount * 100),
                'currency' => strtolower($currency),
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => $metadata,
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $paymentIntent->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'amount' => $paymentIntent->amount,
                    'currency' => $paymentIntent->currency,
                    'status' => $paymentIntent->status,
                ],
                'message' => 'PaymentIntent created.',
            ];
        } catch (ApiErrorException $e) {
            Log::error('Invoice PaymentIntent creation failed', [
                'metadata' => $metadata,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment history for an appointment
     * 
     * @param int $appointmentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPaymentHistory(int $appointmentId)
    {
        return AppointmentPayment::where('appointment_id', $appointmentId)
            ->orderByDesc('created_at')
            ->get();
    }
}
