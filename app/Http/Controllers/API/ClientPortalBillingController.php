<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\InvoicePaymentSyncService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientPortalBillingController extends Controller
{
    /**
     * List of Billing (Invoices)
     * GET /api/billing/list
     *
     * Query params (mandatory):
     * - client_matter_id: integer, required - Filter by client matter
     *
     * Query params (optional):
     * - page: integer, default 1
     * - per_page: integer, default 10
     *
     * Returns invoices where:
     * - client_portal_sent = 1 (invoice sent to client portal)
     * - invoice_status in 0 (Pending/Unpaid), 1 (Paid), 2 (Partial)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        try {
            $request->validate([
                'client_matter_id' => 'required|integer|min:1',
            ]);

            $admin = $request->user();
            $clientId = $admin->id;
            $clientMatterId = $request->get('client_matter_id');
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 10);
            $perPage = min(max($perPage, 1), 100); // Clamp between 1 and 100

            $baseQuery = DB::table('account_client_receipts')
                ->select(
                    'trans_no',
                    'receipt_id',
                    DB::raw('SUM(COALESCE(balance_amount, 0)) as balance_amount'),
                    DB::raw('MAX(COALESCE(invoice_status, 0)) as invoice_status'),
                    DB::raw('MAX(description) as description'),
                    DB::raw('MAX(trans_date) as latest_trans_date'),
                    DB::raw('MAX(client_portal_sent_at) as client_portal_sent_at'),
                    DB::raw('MAX(client_matter_id) as client_matter_id')
                )
                ->where('client_id', $clientId)
                ->where('client_matter_id', $clientMatterId)
                ->where('receipt_type', 3)
                ->where('client_portal_sent', 1)
                ->whereIn('invoice_status', [0, 1, 2])
                ->where(function ($query) {
                    $query->whereNull('void_invoice')
                        ->orWhere('void_invoice', 0);
                })
                ->groupBy('trans_no', 'receipt_id')
                ->orderByDesc(DB::raw('MAX(client_portal_sent_at)'))
                ->orderByDesc(DB::raw('MAX(trans_date)'));

            $total = DB::table(DB::raw('(' . $baseQuery->toSql() . ') as sub'))
                ->mergeBindings($baseQuery)
                ->count();

            $offset = ($page - 1) * $perPage;
            $invoices = (clone $baseQuery)
                ->offset($offset)
                ->limit($perPage)
                ->get();

            $statusMap = [0 => 'Pending', 1 => 'Paid', 2 => 'Partial'];

            $invoices = $invoices->map(function ($invoice) use ($statusMap) {
                return [
                    'trans_no' => $invoice->trans_no,
                    'receipt_id' => $invoice->receipt_id,
                    'balance_amount' => (float) $invoice->balance_amount,
                    'invoice_status' => (int) $invoice->invoice_status,
                    'status' => $statusMap[$invoice->invoice_status] ?? 'Unknown',
                    'description' => $invoice->description,
                    'trans_date' => $invoice->latest_trans_date,
                    'client_portal_sent_at' => $invoice->client_portal_sent_at,
                    'client_matter_id' => $invoice->client_matter_id,
                ];
            });

            $lastPage = (int) ceil($total / $perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoices' => $invoices,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => $lastPage,
                        'from' => $total > 0 ? $offset + 1 : null,
                        'to' => $total > 0 ? min($offset + $perPage, $total) : null,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Billing List API Error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch billing list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Billing invoice Update (Google Pay / Apple Pay / Stripe)
     * POST /api/billing/invoice-update
     *
     * Input (JSON body):
     * - billing_invoice_id: receipt_id from account_client_receipts
     * - client_matter_id: client matter ID (required)
     * - payment_type: "google_pay", "apple_pay", or "stripe"
     * - payment_token: unique token value (e.g. Stripe PaymentIntent id pi_...)
     * - payment_status: "completed" or "failed"
     *
     * Lookup by receipt_id and client_matter_id. When payment_status is "completed": syncs invoice to fully paid
     * (balances / partial_paid / mirror table) then stores payment_token and payment_type.
     * When payment_status is "failed": no update.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateInvoice(Request $request)
    {
        try {
            $validated = $request->validate([
                'billing_invoice_id' => 'required|integer|min:1',
                'client_matter_id' => 'required|integer|min:1',
                'payment_type' => 'required|string|in:google_pay,apple_pay,stripe',
                'payment_token' => 'required|string|max:500',
                'payment_status' => 'required|string|in:completed,failed',
            ]);

            $clientId = $request->user()->id;
            $receiptId = (int) $validated['billing_invoice_id'];
            $clientMatterId = (int) $validated['client_matter_id'];

            $invoiceRow = DB::table('account_client_receipts')
                ->where('receipt_id', $receiptId)
                ->where('client_matter_id', $clientMatterId)
                ->where('client_id', $clientId)
                ->where('receipt_type', 3)
                ->where('client_portal_sent', 1)
                ->orderBy('id')
                ->first(['invoice_no', 'trans_no']);

            if (!$invoiceRow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found or not accessible.',
                ], 404);
            }

            if ($validated['payment_status'] === 'completed') {
                $enforceVerification = (bool) config('services.stripe.enforce_portal_payment_verification', false);

                $rejection = $this->rejectionReasonForPortalPayment(
                    $clientId,
                    $receiptId,
                    $clientMatterId,
                    $invoiceRow,
                    $validated['payment_token']
                );

                if ($rejection !== null) {
                    Log::warning('Client portal invoice payment could not be verified', [
                        'client_id' => $clientId,
                        'receipt_id' => $receiptId,
                        'client_matter_id' => $clientMatterId,
                        'payment_type' => $validated['payment_type'],
                        'reason' => $rejection,
                        'enforced' => $enforceVerification,
                    ]);

                    if ($enforceVerification) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment could not be verified: ' . $rejection,
                        ], 422);
                    }
                }

                $marked = app(InvoicePaymentSyncService::class)->markFullyPaidFromClientPortal(
                    $clientId,
                    $receiptId,
                    $clientMatterId
                );
                if (! $marked) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not update invoice payment state.',
                    ], 422);
                }

                $updated = DB::table('account_client_receipts')
                    ->where('receipt_id', $receiptId)
                    ->where('client_matter_id', $clientMatterId)
                    ->where('client_id', $clientId)
                    ->update([
                        'client_portal_payment_token' => $validated['payment_token'],
                        'client_portal_payment_type' => $validated['payment_type'],
                        'updated_at' => now(),
                    ]);

                if (! $updated) {
                    // Invoice is paid but the token did not store — flag it, the audit
                    // trail for this payment is now incomplete.
                    Log::error('Invoice marked paid but payment token was not stored', [
                        'client_id' => $clientId,
                        'receipt_id' => $receiptId,
                        'client_matter_id' => $clientMatterId,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Invoice payment recorded successfully.',
                    'data' => [
                        'receipt_id' => $receiptId,
                        'invoice_status' => 1,
                        'updated' => (bool) $updated,
                    ],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment status is failed; no update performed.',
                'data' => [
                    'receipt_id' => $receiptId,
                    'payment_status' => 'failed',
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Billing invoice Update API Error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update billing invoice.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a PaymentIntent for a portal invoice. The amount comes from the invoice,
     * so the caller cannot pay a token amount to clear a large balance, and the invoice
     * identifiers are stamped into metadata so the payment can be bound back on return.
     *
     * POST /api/billing/create-payment-intent
     */
    public function createPaymentIntent(Request $request)
    {
        try {
            $validated = $request->validate([
                'billing_invoice_id' => 'required|integer|min:1',
                'client_matter_id' => 'required|integer|min:1',
            ]);

            $clientId = $request->user()->id;
            $receiptId = (int) $validated['billing_invoice_id'];
            $clientMatterId = (int) $validated['client_matter_id'];

            $invoiceRow = DB::table('account_client_receipts')
                ->where('receipt_id', $receiptId)
                ->where('client_matter_id', $clientMatterId)
                ->where('client_id', $clientId)
                ->where('receipt_type', 3)
                ->where('client_portal_sent', 1)
                ->orderBy('id')
                ->first(['invoice_no', 'trans_no']);

            if (! $invoiceRow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found or not accessible.',
                ], 404);
            }

            $outstanding = $this->outstandingForInvoice($clientId, $invoiceRow);

            if ($outstanding <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This invoice has nothing outstanding to pay.',
                ], 422);
            }

            $result = app(StripePaymentService::class)->createInvoicePaymentIntent($outstanding, [
                'receipt_id' => (string) $receiptId,
                'client_matter_id' => (string) $clientMatterId,
                'client_id' => (string) $clientId,
            ]);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'PaymentIntent created.',
                'data' => $result['data'],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Billing PaymentIntent API Error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent.',
            ], 500);
        }
    }

    /**
     * Why this portal payment cannot be trusted, or null when it verifies.
     */
    private function rejectionReasonForPortalPayment(
        int $clientId,
        int $receiptId,
        int $clientMatterId,
        object $invoiceRow,
        string $paymentToken
    ): ?string {
        // A token already used on a different invoice means a replayed payment.
        $reusedElsewhere = DB::table('account_client_receipts')
            ->where('client_portal_payment_token', $paymentToken)
            ->where(function ($q) use ($receiptId, $clientId) {
                $q->where('receipt_id', '!=', $receiptId)
                    ->orWhere('client_id', '!=', $clientId);
            })
            ->exists();

        if ($reusedElsewhere) {
            return 'This payment token has already been recorded against another invoice.';
        }

        $outstanding = $this->outstandingForInvoice($clientId, $invoiceRow);

        $verification = app(StripePaymentService::class)->verifyPaymentIntentForInvoice(
            $paymentToken,
            $outstanding,
            [
                'receipt_id' => (string) $receiptId,
                'client_matter_id' => (string) $clientMatterId,
                'client_id' => (string) $clientId,
            ]
        );

        return $verification['verified'] ? null : ($verification['reason'] ?? 'Payment could not be verified.');
    }

    /**
     * Outstanding amount for an invoice, falling back to its total when payment
     * state cannot be computed.
     */
    private function outstandingForInvoice(int $clientId, object $invoiceRow): float
    {
        $invoiceKey = ! empty($invoiceRow->invoice_no)
            ? (string) $invoiceRow->invoice_no
            : (string) ($invoiceRow->trans_no ?? '');

        if ($invoiceKey === '') {
            return 0.0;
        }

        $sync = app(InvoicePaymentSyncService::class);
        $state = $sync->computePaymentState($clientId, $invoiceKey);

        if ($state !== null) {
            return (float) $state['new_balance'];
        }

        return $sync->sumInvoiceLineWithdrawTotal($clientId, $invoiceKey);
    }
}
