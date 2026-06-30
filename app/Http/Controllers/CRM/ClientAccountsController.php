<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Admin;
use App\Models\ActivitiesLog;
// clientServiceTaken model removed - table client_service_takens does not exist
use App\Models\AccountClientReceipt;
use App\Models\AccountAllInvoiceReceipt;
use App\Models\ClientMatter;
use App\Mail\HubdocInvoiceMail;
use App\Services\ClientPortalActionNoteService;
use App\Services\FinancialStatsService;
use App\Services\InvoicePaymentSyncService;
use App\Services\FCMService;
use App\Services\SystemEmailLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;

/**
 * ClientAccountsController
 * 
 * Handles all accounting operations including invoices, receipts,
 * ledger management, and financial transactions.
 * 
 * Maps to: resources/views/Admin/clients/tabs/accounts.blade.php
 */
class ClientAccountsController extends Controller
{
    use EnsuresCrmRecordAccess;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    protected function ensureAccountsClientFromRequest(Request $request): void
    {
        if ($request->filled('client_id')) {
            $this->ensureCrmRecordAccess((int) $request->input('client_id'));
        }
    }

    /**
     * Public URL for a path on the S3 disk (IDE-friendly wrapper; Storage::disk returns a contract without url() in typings).
     */
    protected function s3PublicUrl(string $filePath): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($filePath);
    }

    /**
     * Apply enhanced date filtering to query
     * Supports quick presets, custom date range, and financial year
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    private function applyDateFilters($query, Request $request)
    {
        $dateFilterType = $request->input('date_filter_type');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $financialYear = $request->input('financial_year');

        // Helper function to format date as dd/mm/yyyy for PostgreSQL TO_DATE()
        // trans_date is VARCHAR stored in dd/mm/yyyy format, so we need to use TO_DATE() for proper comparison
        $formatDateForQuery = function($carbonDate) {
            return $carbonDate->format('d/m/Y');
        };

        if ($dateFilterType && $dateFilterType !== 'custom' && $dateFilterType !== 'financial_year') {
            // Quick Filter Presets
            $now = \Carbon\Carbon::now();
            $startDate = null;
            $endDate = null;

            switch ($dateFilterType) {
                case 'today':
                    $startDate = $now->copy()->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    break;
                
                case 'this_week':
                    $startDate = $now->copy()->startOfWeek();
                    $endDate = $now->copy()->endOfWeek();
                    break;
                
                case 'this_month':
                    $startDate = $now->copy()->startOfMonth();
                    $endDate = $now->copy()->endOfMonth();
                    break;
                
                case 'this_quarter':
                    $startDate = $now->copy()->startOfQuarter();
                    $endDate = $now->copy()->endOfQuarter();
                    break;
                
                case 'this_year':
                    $startDate = $now->copy()->startOfYear();
                    $endDate = $now->copy()->endOfYear();
                    break;
                
                case 'last_month':
                    $startDate = $now->copy()->subMonth()->startOfMonth();
                    $endDate = $now->copy()->subMonth()->endOfMonth();
                    break;
                
                case 'last_quarter':
                    $startDate = $now->copy()->subQuarter()->startOfQuarter();
                    $endDate = $now->copy()->subQuarter()->endOfQuarter();
                    break;
                
                case 'last_year':
                    $startDate = $now->copy()->subYear()->startOfYear();
                    $endDate = $now->copy()->subYear()->endOfYear();
                    break;
            }

            if ($startDate && $endDate) {
                // PostgreSQL: Convert VARCHAR dd/mm/yyyy to DATE for proper comparison
                $startDateStr = $formatDateForQuery($startDate);
                $endDateStr = $formatDateForQuery($endDate);
                $query->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr]);
            }
        } 
        elseif ($fromDate && $toDate) {
            // Custom Date Range - dates are already in dd/mm/yyyy format from datepicker
            // Validate format and use TO_DATE() for proper comparison
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fromDate) && preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $toDate)) {
                $query->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$fromDate, $toDate]);
            }
        }
        elseif ($financialYear) {
            // Financial Year (e.g., "2023-2024" means July 1, 2023 to June 30, 2024)
            $years = explode('-', $financialYear);
            if (count($years) == 2) {
                $fyStartDate = \Carbon\Carbon::createFromDate($years[0], 7, 1)->startOfDay(); // July 1st
                $fyEndDate = \Carbon\Carbon::createFromDate($years[1], 6, 30)->endOfDay();   // June 30th
                
                $startDateStr = $formatDateForQuery($fyStartDate);
                $endDateStr = $formatDateForQuery($fyEndDate);
                $query->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr]);
            }
        }
    }

    /**
     * ================================================================
     * CLIENT FUND RECEIPTS & LEDGER
     * ================================================================
     */

    public function saveaccountreport(Request $request, $id = NULL)
    {
        try {
            $requestData = $request->all();
            $response = [];
            
            // Validate required fields
            if (empty($requestData['client_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client ID is required',
                    'requestData' => [],
                    'awsUrl' => "",
                    'invoices' => []
                ], 400);
            }

            $this->ensureCrmRecordAccess((int) $requestData['client_id']);

            // Handle document upload
            $insertedDocId = null;
            $doc_saved = false;
            $client_unique_id = "";
            $awsUrl = "";
            $doctype = isset($request->doctype) ? $request->doctype : '';
   
        if ($request->hasfile('document_upload')) {
            $files = is_array($request->file('document_upload')) ? $request->file('document_upload') : [$request->file('document_upload')];
   
            $client_info = \App\Models\Admin::select('client_id')->where('id', $requestData['client_id'])->first();
            $client_unique_id = !empty($client_info) ? $client_info->client_id : "";
   
            foreach ($files as $file) {
                $size = $file->getSize();
                $fileName = $file->getClientOriginalName();
                $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
                $fileExtension = $file->getClientOriginalExtension();
                $name = time() . $file->getClientOriginalName();
                $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
                Storage::disk('s3')->put($filePath, file_get_contents($file));
   
                $obj = new \App\Models\Document;
                $obj->file_name = $nameWithoutExtension;
                $obj->filetype = $fileExtension;
                $obj->user_id = Auth::user()->id;
                $obj->myfile = $this->s3PublicUrl($filePath);
                $obj->myfile_key = $name;
                $obj->client_id = $requestData['client_id'];
                $obj->type = $request->type;
                $obj->file_size = $size;
                $obj->doc_type = $doctype;
                $doc_saved = $obj->save();
                $insertedDocId = $obj->id;
            }
        } else {
            $insertedDocId = null;
            $doc_saved = "";
        }
   
        if (isset($requestData['trans_date'])) {
            $ledgerPaymentMethodAt = function (int $index) use ($requestData): string {
                if (empty($requestData['payment_method']) || ! is_array($requestData['payment_method'])) {
                    return '';
                }

                return trim((string) ($requestData['payment_method'][$index] ?? ''));
            };

            $ledgerSurchargeAt = function (int $index) use ($requestData, $ledgerPaymentMethodAt): float {
                $type = trim((string) ($requestData['client_fund_ledger_type'][$index] ?? ''));
                if ($type !== 'Deposit' || $ledgerPaymentMethodAt($index) !== 'EFTPOS') {
                    return 0.0;
                }
                if (empty($requestData['eftpos_surcharge_amount']) || ! is_array($requestData['eftpos_surcharge_amount'])) {
                    return 0.0;
                }

                return round(max(0, floatval($requestData['eftpos_surcharge_amount'][$index] ?? 0)), 2);
            };

            // Generate unique receipt id
            $is_record_exist = DB::table('account_client_receipts')->select('receipt_id')->where('receipt_type', 1)->orderBy('receipt_id', 'desc')->first();
            $receipt_id = !$is_record_exist ? 1 : $is_record_exist->receipt_id + 1;
   
			$finalArr = [];
			$ledgerBalanceQuery = DB::table('account_client_receipts')
				->select('deposit_amount', 'withdraw_amount', 'void_fee_transfer')
				->where('client_id', $requestData['client_id'])
				->where('receipt_type', 1);

			if (!empty($requestData['client_matter_id'])) {
				$ledgerBalanceQuery->where('client_matter_id', $requestData['client_matter_id']);
			}

			$existingLedgerEntries = $ledgerBalanceQuery->get();
			$running_balance = 0;
			foreach ($existingLedgerEntries as $existingEntry) {
				if (isset($existingEntry->void_fee_transfer) && $existingEntry->void_fee_transfer == 1) {
					continue;
				}
				$running_balance += floatval($existingEntry->deposit_amount) - floatval($existingEntry->withdraw_amount);
			}
            $saved = false;
   
            // Group entries by invoice_no for Fee Transfer
            $feeTransferByInvoice = [];
            for ($i = 0; $i < count($requestData['trans_date']); $i++) {
                $clientFundLedgerType = $requestData['client_fund_ledger_type'][$i];
                $invoiceNo = isset($requestData['invoice_no'][$i]) && $requestData['invoice_no'][$i] != "" ? $requestData['invoice_no'][$i] : null;
   
                if ($clientFundLedgerType === 'Fee Transfer' && $invoiceNo) {
                    if (!isset($feeTransferByInvoice[$invoiceNo])) {
                        $feeTransferByInvoice[$invoiceNo] = [];
                    }
                    $feeTransferByInvoice[$invoiceNo][] = [
                        'index' => $i,
                        'withdraw_amount' => floatval($requestData['withdraw_amount'][$i] ?? 0),
                        'trans_date' => $requestData['trans_date'][$i],
                        'entry_date' => $requestData['entry_date'][$i],
                        'description' => $requestData['description'][$i],
                    ];
                }
            }
   
            // Process Fee Transfers with invoice numbers
            $invoicePaymentSync = app(InvoicePaymentSyncService::class);
            foreach ($feeTransferByInvoice as $invoiceNo => $feeTransfers) {
                $totalWithdrawAmount = array_sum(array_column($feeTransfers, 'withdraw_amount'));
   
                // Validate Fee Transfer amount against Current Funds Held
                // Calculate balance excluding voided fee transfers
                $ledger_entries = DB::table('account_client_receipts')
                    ->select('deposit_amount', 'withdraw_amount', 'void_fee_transfer')
                    ->where('client_id', $requestData['client_id'])
                    ->where('receipt_type', 1);
                
                if (!empty($requestData['client_matter_id'])) {
                    $ledger_entries->where('client_matter_id', $requestData['client_matter_id']);
                }
                
                $ledger_entries = $ledger_entries->get();
                
                $currentFundsHeld = 0;
			foreach($ledger_entries as $entry) {
				// Skip voided fee transfers
				if(isset($entry->void_fee_transfer) && $entry->void_fee_transfer == 1) {
					continue;
				}
				$currentFundsHeld += floatval($entry->deposit_amount) - floatval($entry->withdraw_amount);
			}
			$currentFundsHeld = round($currentFundsHeld, 2);
			$totalWithdrawAmount = round($totalWithdrawAmount, 2);
			
			if ($totalWithdrawAmount > $currentFundsHeld) {
                    $response['status'] = false;
                    $response['message'] = 'You cannot transfer the amount greater than of Current Funds Held amount (Current: $' . number_format($currentFundsHeld, 2) . ')';
                    $response['requestData'] = [];
                    $response['awsUrl'] = "";
                    $response['invoices'] = [];
                    return response()->json($response, 200);
                }
   
                if (! $invoicePaymentSync->invoiceLinesExist($requestData['client_id'], $invoiceNo)) {
                    continue;
                }

                $invoiceWithdrawAmount = $invoicePaymentSync->sumInvoiceLineWithdrawTotal($requestData['client_id'], $invoiceNo);

                // Recalculate current total payments from all sources (office receipts + fee transfers)
                    // This ensures we have accurate data even if office receipts were applied first
                    $currentTotalPaidOffice = DB::table('account_client_receipts')
                        ->where('receipt_type', 2)
                        ->where('invoice_no', $invoiceNo)
                        ->where('client_id', $requestData['client_id'])
                        ->where('save_type', 'final')
                        ->sum('deposit_amount');
                    
                    $currentTotalPaidFeeTransfer = DB::table('account_client_receipts')
                        ->where('receipt_type', 1)
                        ->where('client_fund_ledger_type', 'Fee Transfer')
                        ->where('invoice_no', $invoiceNo)
                        ->where('client_id', $requestData['client_id'])
                        ->where(function($q) {
                            $q->whereNull('void_fee_transfer')
                              ->orWhere('void_fee_transfer', 0);
                        })
                        ->sum('withdraw_amount');
                    
                    $currentTotalPaid = $currentTotalPaidOffice + $currentTotalPaidFeeTransfer;
                    $currentBalance = $invoiceWithdrawAmount - $currentTotalPaid;

                    // Process Fee Transfers
                    $remainingWithdraw = $totalWithdrawAmount;
                    $totalNewFeeTransferAmount = 0; // Track total amount of new fee transfers being created
                    $firstFeeTransferTransNo = null; // Track first fee transfer trans_no for residual description

                    foreach ($feeTransfers as $feeTransfer) {
                        $i = $feeTransfer['index'];
                        if ($remainingWithdraw <= 0) break;

                        $amountToUse = min($remainingWithdraw, $feeTransfer['withdraw_amount']);
                        if ($amountToUse <= 0) continue;

                        // Adjust amount if it exceeds the invoice's withdraw amount
                        // Use recalculated current total + new fee transfer amount
                        if ($currentTotalPaid + $totalNewFeeTransferAmount + $amountToUse > $invoiceWithdrawAmount) {
                            $amountToUse = $invoiceWithdrawAmount - $currentTotalPaid - $totalNewFeeTransferAmount;
                        }

                        if ($amountToUse <= 0) continue;
                        
                        // Track this amount for next iteration
                        $totalNewFeeTransferAmount += $amountToUse;
   
                        $trans_no = $this->createTransactionNumber('Fee Transfer');
                        if ($firstFeeTransferTransNo === null) {
                            $firstFeeTransferTransNo = $trans_no; // Store first trans_no for residual description
                        }
                        $deposit = 0;
                        $withdraw = $amountToUse;
   
                        $running_balance += $deposit - $withdraw;
   
                        $saved = DB::table('account_client_receipts')->insert([
                            'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                            'client_id' => $requestData['client_id'],
                            'client_matter_id' => $requestData['client_matter_id'] ?? null,
                            'receipt_id' => $receipt_id,
                            'receipt_type' => $requestData['receipt_type'],
                            'trans_date' => $feeTransfer['trans_date'],
                            'entry_date' => $feeTransfer['entry_date'],
                            'invoice_no' => $invoiceNo,
                            'trans_no' => $trans_no,
                            'client_fund_ledger_type' => 'Fee Transfer',
                            'description' => $feeTransfer['description'],
                            'deposit_amount' => $deposit,
                            'withdraw_amount' => $amountToUse,
                            'balance_amount' => $running_balance,
                            'payment_method' => $ledgerPaymentMethodAt($feeTransfer['index']),
                            'eftpos_surcharge_amount' => null,
                            'uploaded_doc_id' => $insertedDocId,
                            'validate_receipt' => 0,
                            'void_invoice' => 0,
                            'invoice_status' => 0,
                            'save_type' => 'final',
                            'hubdoc_sent' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
   
                        $finalArr[] = [
                            'trans_date' => $feeTransfer['trans_date'],
                            'entry_date' => $feeTransfer['entry_date'],
                            'client_fund_ledger_type' => 'Fee Transfer',
                            'trans_no' => $trans_no,
                            'invoice_no' => $invoiceNo,
                            'description' => $feeTransfer['description'],
                            'deposit_amount' => $deposit,
                            'withdraw_amount' => $amountToUse,
                            'balance_amount' => $running_balance,
                            'payment_method' => $ledgerPaymentMethodAt($feeTransfer['index']),
                            'eftpos_surcharge_amount' => null,
                        ];

                        $remainingWithdraw -= $amountToUse;
                    }
   
                    // Handle excess amount by creating residual client fund deposit (like office receipts)
                    if ($remainingWithdraw > 0) {
                        // Check if remaining amount would exceed invoice
                        $maxAllowed = $invoiceWithdrawAmount - $currentTotalPaid - $totalNewFeeTransferAmount;
                        $excessAmount = $remainingWithdraw;
                        
                        // If invoice is not fully paid yet, apply remaining amount up to invoice limit
                        if ($maxAllowed > 0) {
                            $withdraw = min($remainingWithdraw, $maxAllowed);
                            $excessAmount = $remainingWithdraw - $withdraw;
                            
                            if ($withdraw > 0) {
                                $trans_no = $this->createTransactionNumber('Fee Transfer');
                                if ($firstFeeTransferTransNo === null) {
                                    $firstFeeTransferTransNo = $trans_no;
                                }

                                $running_balance += 0 - $withdraw;
                                $totalNewFeeTransferAmount += $withdraw;

                                $saved = DB::table('account_client_receipts')->insert([
                                    'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                                    'client_id' => $requestData['client_id'],
                                    'client_matter_id' => $requestData['client_matter_id'] ?? null,
                                    'receipt_id' => $receipt_id,
                                    'receipt_type' => $requestData['receipt_type'],
                                    'trans_date' => $feeTransfers[0]['trans_date'],
                                    'entry_date' => $feeTransfers[0]['entry_date'],
                                    'invoice_no' => $invoiceNo,
                                    'trans_no' => $trans_no,
                                    'client_fund_ledger_type' => 'Fee Transfer',
                                    'description' => $feeTransfers[0]['description'],
                                    'deposit_amount' => 0,
                                    'withdraw_amount' => $withdraw,
                                    'balance_amount' => $running_balance,
                                    'payment_method' => $ledgerPaymentMethodAt($feeTransfers[0]['index']),
                                    'eftpos_surcharge_amount' => null,
                                    'uploaded_doc_id' => $insertedDocId,
                                    'validate_receipt' => 0,
                                    'void_invoice' => 0,
                                    'invoice_status' => 0,
                                    'save_type' => 'final',
                                    'hubdoc_sent' => 0,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                $finalArr[] = [
                                    'trans_date' => $feeTransfers[0]['trans_date'],
                                    'entry_date' => $feeTransfers[0]['entry_date'],
                                    'client_fund_ledger_type' => 'Fee Transfer',
                                    'trans_no' => $trans_no,
                                    'invoice_no' => $invoiceNo,
                                    'description' => $feeTransfers[0]['description'],
                                    'deposit_amount' => 0,
                                    'withdraw_amount' => $withdraw,
                                    'balance_amount' => $running_balance,
                                    'payment_method' => $ledgerPaymentMethodAt($feeTransfers[0]['index']),
                                    'eftpos_surcharge_amount' => null,
                                ];
                            }
                        }
                        
                        // Create residual client fund deposit for excess amount (same behavior as office receipts)
                        if ($excessAmount > 0.01) { // Allow for small rounding differences
                            $residualTransNo = $this->createTransactionNumber('Deposit');
                            $residualDescription = 'Residual from Fee Transfer';
                            if ($firstFeeTransferTransNo) {
                                $residualDescription .= ' ' . $firstFeeTransferTransNo;
                            }
                            $residualDescription .= ' - Applied to ' . $invoiceNo;
                            
                            // Deposit the excess amount back to client funds
                            $running_balance += $excessAmount;

                            $residualReceiptId = DB::table('account_client_receipts')->insertGetId([
                                'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                                'client_id' => $requestData['client_id'],
                                'client_matter_id' => $requestData['client_matter_id'] ?? null,
                                'receipt_id' => $receipt_id,
                                'receipt_type' => $requestData['receipt_type'],
                                'trans_date' => $feeTransfers[0]['trans_date'],
                                'entry_date' => $feeTransfers[0]['entry_date'],
                                'trans_no' => $residualTransNo,
                                'invoice_no' => null, // Unallocated - can be allocated to other invoices later
                                'client_fund_ledger_type' => 'Deposit',
                                'description' => $residualDescription,
                                'deposit_amount' => $excessAmount,
                                'withdraw_amount' => 0,
                                'balance_amount' => $running_balance,
                                'payment_method' => $ledgerPaymentMethodAt($feeTransfers[0]['index']),
                                'eftpos_surcharge_amount' => null,
                                'uploaded_doc_id' => $insertedDocId,
                                'extra_amount_receipt' => 'residual',
                                'validate_receipt' => 0,
                                'void_invoice' => 0,
                                'invoice_status' => 0,
                                'save_type' => 'final',
                                'hubdoc_sent' => 0,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $finalArr[] = [
                                'trans_date' => $feeTransfers[0]['trans_date'],
                                'entry_date' => $feeTransfers[0]['entry_date'],
                                'client_fund_ledger_type' => 'Deposit',
                                'trans_no' => $residualTransNo,
                                'invoice_no' => null,
                                'description' => $residualDescription,
                                'deposit_amount' => $excessAmount,
                                'withdraw_amount' => 0,
                                'balance_amount' => $running_balance,
                                'extra_amount_receipt' => 'residual',
                                'payment_method' => $ledgerPaymentMethodAt($feeTransfers[0]['index']),
                                'eftpos_surcharge_amount' => null,
                            ];

                            Log::info('Residual client fund deposit created from fee transfer', [
                                'invoice_no' => $invoiceNo,
                                'fee_transfer_trans_no' => $firstFeeTransferTransNo,
                                'residual_receipt_id' => $residualReceiptId,
                                'residual_trans_no' => $residualTransNo,
                                'excess_amount' => $excessAmount,
                                'allocated_amount' => $totalNewFeeTransferAmount,
                            ]);
                        }
                    }

                    $synced = $invoicePaymentSync->persistPaymentState($requestData['client_id'], $invoiceNo);
                    if ($synced !== null) {
                        $response['invoices'][] = [
                            'invoice_no' => $invoiceNo,
                            'invoice_status' => $synced['invoice_status'],
                            'invoice_balance' => $synced['new_balance'],
                            'outstanding_balance' => $synced['new_balance'],
                        ];
                    }
            }
   
            // Process remaining entries (non-Fee Transfer or Fee Transfer without invoice)
            for ($i = 0; $i < count($requestData['trans_date']); $i++) {
                $clientFundLedgerType = $requestData['client_fund_ledger_type'][$i];
                $invoiceNo = isset($requestData['invoice_no'][$i]) && $requestData['invoice_no'][$i] != "" ? $requestData['invoice_no'][$i] : null;
   
                // Skip Fee Transfers with invoice numbers as they are already processed
                if ($clientFundLedgerType === 'Fee Transfer' && $invoiceNo) {
                    continue;
                }
   
                $trans_no = $this->createTransactionNumber($clientFundLedgerType);
                $principal = floatval($requestData['deposit_amount'][$i] ?? 0);
                $eftposSurcharge = $ledgerSurchargeAt($i);
                $deposit = ($clientFundLedgerType === 'Deposit') ? round($principal + $eftposSurcharge, 2) : $principal;
                $withdraw = floatval($requestData['withdraw_amount'][$i] ?? 0);
   
                // Validate Fee Transfer amount against Current Funds Held (for Fee Transfer without invoice)
                if ($clientFundLedgerType === 'Fee Transfer' && !$invoiceNo) {
                    // Calculate balance excluding voided fee transfers
                    $ledger_entries = DB::table('account_client_receipts')
                        ->select('deposit_amount', 'withdraw_amount', 'void_fee_transfer')
                        ->where('client_id', $requestData['client_id'])
                        ->where('receipt_type', 1);
                    
                    if (!empty($requestData['client_matter_id'])) {
                        $ledger_entries->where('client_matter_id', $requestData['client_matter_id']);
                    }
                    
                    $ledger_entries = $ledger_entries->get();
                    
                    $currentFundsHeld = 0;
			foreach($ledger_entries as $entry) {
				// Skip voided fee transfers
				if(isset($entry->void_fee_transfer) && $entry->void_fee_transfer == 1) {
					continue;
				}
				$currentFundsHeld += floatval($entry->deposit_amount) - floatval($entry->withdraw_amount);
			}
			$currentFundsHeld = round($currentFundsHeld, 2);
			$withdraw = round($withdraw, 2);
			
			if ($withdraw > $currentFundsHeld) {
                        $response['status'] = false;
                        $response['message'] = 'You cannot transfer the amount greater than of Current Funds Held amount (Current: $' . number_format($currentFundsHeld, 2) . ')';
                        $response['requestData'] = [];
                        $response['awsUrl'] = "";
                        $response['invoices'] = [];
                        return response()->json($response, 200);
                    }
                }
   
                $running_balance += $deposit - $withdraw;
   
                $saved = DB::table('account_client_receipts')->insert([
                    'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                    'client_id' => $requestData['client_id'],
                     'client_matter_id' => $requestData['client_matter_id'] ?? null,
                    'receipt_id' => $receipt_id,
                    'receipt_type' => $requestData['receipt_type'],
                    'trans_date' => $requestData['trans_date'][$i],
                    'entry_date' => $requestData['entry_date'][$i],
                    'invoice_no' => $invoiceNo ?? null,
                    'trans_no' => $trans_no,
                    'client_fund_ledger_type' => $clientFundLedgerType,
                    'description' => $requestData['description'][$i],
                    'deposit_amount' => $deposit,
                    'withdraw_amount' => $withdraw,
                    'balance_amount' => $running_balance,
                    'payment_method' => $ledgerPaymentMethodAt($i),
                    'eftpos_surcharge_amount' => ($eftposSurcharge > 0 ? $eftposSurcharge : null),
                    'uploaded_doc_id' => $insertedDocId,
                    'validate_receipt' => 0,
                    'void_invoice' => 0,
                    'invoice_status' => 0,
                    'save_type' => 'final',
                    'hubdoc_sent' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
   
                $finalArr[] = [
                    'trans_date' => $requestData['trans_date'][$i],
                    'entry_date' => $requestData['entry_date'][$i],
                    'client_fund_ledger_type' => $clientFundLedgerType,
                    'trans_no' => $trans_no,
                    'invoice_no' => $invoiceNo ?? '',
                    'description' => $requestData['description'][$i],
                    'deposit_amount' => $deposit,
                    'withdraw_amount' => $withdraw,
                    'balance_amount' => $running_balance,
                    'payment_method' => $ledgerPaymentMethodAt($i),
                    'eftpos_surcharge_amount' => ($eftposSurcharge > 0 ? $eftposSurcharge : null),
                ];
            }
   
            // Log activity
            if ($saved && !empty($finalArr)) {
                // Get the last transaction details for logging
                $lastEntry = end($finalArr);
                $lastTransDate = $lastEntry['trans_date'] ?? '';
                $lastDeposit = $lastEntry['deposit_amount'] ?? 0;
                $lastWithdraw = $lastEntry['withdraw_amount'] ?? 0;
                $lastTransNo = $lastEntry['trans_no'] ?? $trans_no;
                
                $subject = $doc_saved ? 'added client funds ledger with its document. Reference no- ' . $lastTransNo : 'added client funds ledger. Reference no- ' . $lastTransNo;
                $description = "Transaction Date: {$lastTransDate}, " . ($lastDeposit > 0 ? "Deposit: \${$lastDeposit}" : "Withdrawal: \${$lastWithdraw}") . ", Balance: \${$running_balance}";
                if ($request->type == 'client') {
                    $objs = new \App\Models\ActivitiesLog;
                    $objs->client_id = $requestData['client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->description = $description;
                    $objs->subject = $subject;
                    $objs->activity_type = 'financial';
                    $objs->task_status = 0;
                    $objs->pin = 0;
                    $objs->save();
                }
            }
        }
   
        // Prepare response
        if ($saved) {
            $response['status'] = true;
            $response['requestData'] = $finalArr;
            $response['db_total_balance_amount'] = $running_balance;
            $response['message'] = $doc_saved ? 'Client receipt with document added successfully' : 'Client receipt added successfully';
            if ($doc_saved && isset($name) && !empty($name)) {
                $url = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                $awsUrl = $url . $client_unique_id . '/' . $doctype . '/' . $name;
                $response['awsUrl'] = $awsUrl;
            } else {
                $response['awsUrl'] = "";
            }
        } else {
            $response['status'] = false;
            $response['requestData'] = [];
            $response['awsUrl'] = "";
            $response['message'] = 'Please try again';
            $response['invoices'] = [];
        }
   
        return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error('Error in saveaccountreport: ' . $e->getMessage(), [
                'request_data' => $request->except(['document_upload']),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while saving the account report. Please try again.',
                'requestData' => [],
                'awsUrl' => "",
                'invoices' => []
            ], 500);
        }
    }
   
    private function createTransactionNumber($clientFundLedgerType)
    {
        switch ($clientFundLedgerType) {
            case 'Deposit':
                $prefix = 'CFL';
                break;
            case 'Fee Transfer':
                $prefix = 'FEE';
                break;
            case 'Disbursement':
                $prefix = 'DIS';
                break;
            case 'Refund':
                $prefix = 'REF';
                break;
            default:
                $prefix = 'CFL';
        }
   
        $latestTrans = DB::table('account_client_receipts')
            ->select('trans_no')
            ->where('receipt_type', 1)
            ->where('client_fund_ledger_type', $clientFundLedgerType)
            ->where('trans_no', 'LIKE', "$prefix-%")
            ->orderBy('id', 'desc')
            ->first();
   
        if (!$latestTrans) {
            $nextNumber = 1;
        } else {
            $lastTransNo = explode('-', $latestTrans->trans_no);
            $lastNumber = isset($lastTransNo[1]) ? (int)$lastTransNo[1] : 0;
            $nextNumber = $lastNumber + 1;
        }
   
        return $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
       }
   
       //Save adjust invoice reports
    public function saveadjustinvoicereport(Request $request, $id = NULL)
    {
        try {
            $requestData = $request->all();
            $response = [];
            $lastInsertId = null;
            $finalArr = [];
            $totalWithdrawAmount = 0;
            
            // Validate required fields
            if (empty($requestData['client_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client ID is required',
                    'requestData' => [],
                    'function_type' => $requestData['function_type'] ?? 'add'
                ], 400);
            }

            $this->ensureCrmRecordAccess((int) $requestData['client_id']);
            
            if( $requestData['function_type'] == 'add')
        {
            $invoice_no = null;
            if(isset($requestData['trans_date'])){
                $rawInvoiceNo = isset($requestData['invoice_no']) ? trim((string) $requestData['invoice_no']) : '';
                if ($rawInvoiceNo !== '' && ! preg_match('/^INV-\d+$/', $rawInvoiceNo)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid invoice number format.',
                        'requestData' => [],
                        'function_type' => $requestData['function_type'] ?? 'add',
                    ], 422);
                }

                $invoice_no = $this->createInvoiceNumber('INV');

                //Generate unique receipt id
                $is_record_exist = DB::table('account_client_receipts')->select('receipt_id')->where('receipt_type',3)->orderBy('receipt_id', 'desc')->first();
                if(!$is_record_exist){
                    $receipt_id = 1;
                } else {
                    $receipt_id = $is_record_exist->receipt_id +1;
                }
                $finalArr = array();
                $totalWithdrawAmount = 0;
                $invoice_status = 0;
                $matterId = $requestData['client_matter_id'] ?? null;

                for($i=0; $i<count($requestData['trans_date']); $i++){
                    $finalArr[$i]['trans_date'] = $requestData['trans_date'][$i];
                    $finalArr[$i]['entry_date'] = $requestData['entry_date'][$i];
                    $finalArr[$i]['trans_no'] = $invoice_no;
                    $finalArr[$i]['gst_included'] = $requestData['gst_included'][$i];
                    $finalArr[$i]['payment_type'] = $requestData['payment_type'][$i];
                    $finalArr[$i]['description'] = $requestData['description'][$i];
                    $finalArr[$i]['withdraw_amount'] = $requestData['withdraw_amount'][$i];
                    $finalArr[$i]['balance_amount'] = $requestData['withdraw_amount'][$i];
                    $finalArr[$i]['invoice_no'] = $invoice_no;
                    $finalArr[$i]['save_type'] = $requestData['save_type'];
                    $finalArr[$i]['receipt_id'] = $receipt_id;
   
                    $finalArr[$i]['invoice_status'] = $invoice_status;
   
                    $lastInsertId    = AccountAllInvoiceReceipt::insertGetId([
                        'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                        'client_id' =>  $requestData['client_id'],
                        'client_matter_id' => $matterId,
                        'receipt_id'=>  $receipt_id,
                        'receipt_type' => $requestData['receipt_type'],
                        'trans_date' => $requestData['trans_date'][$i],
                        'entry_date' => $requestData['entry_date'][$i],
                        'gst_included' => $requestData['gst_included'][$i],
                        'payment_type' => $requestData['payment_type'][$i],
                        'trans_no' => $invoice_no,
                        'description' => $requestData['description'][$i],
                        'withdraw_amount' => $requestData['withdraw_amount'][$i],
                        'invoice_no' => $invoice_no,
                        'save_type' => $requestData['save_type'],
                        'invoice_status' => $invoice_status,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $finalArr[$i]['id'] = $lastInsertId;
   
                    //Save to activity log
                    $subject = 'added invoice.Reference no- '.$invoice_no;
                    $objs = new ActivitiesLog;
                    $objs->client_id = $requestData['client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->description = '';
                    $objs->subject = $subject;
                    $objs->activity_type = 'financial';
                    $objs->task_status = 0;
                    $objs->pin = 0;
                    $objs->save();
   
                    $amount11 = floatval($requestData['withdraw_amount'][$i]);
                    $totalWithdrawAmount += $amount11;
                } //end for loop
   
                //main table 'account_client_receipts' entry
                $lastInsertId    = DB::table('account_client_receipts')->insertGetId([
                    'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                    'client_id' =>  $requestData['client_id'],
                    'client_matter_id' => $matterId,
                    'receipt_id'=>  $receipt_id,
                    'receipt_type' => $requestData['receipt_type'],
                    'trans_date' => $requestData['trans_date'][0],
                    'entry_date' => $requestData['entry_date'][0],
                    'gst_included' => $requestData['gst_included'][0],
                    'payment_type' => $requestData['payment_type'][0],
                    'trans_no' => $invoice_no,
                    'description' => $requestData['description'][0],
                    'withdraw_amount' => $totalWithdrawAmount,
                    'balance_amount' => $totalWithdrawAmount,
                    'invoice_no' => $invoice_no,
                    'save_type' => $requestData['save_type'],
                    'invoice_status' => $invoice_status,
                    'validate_receipt' => 0,
                    'void_invoice' => 0,
                    'hubdoc_sent' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            if($lastInsertId) {
                $response['requestData']     = $finalArr;
                $response['status']     =     true;
                $response['message']    =    'Invoice added successfully';
                $response['function_type'] = $requestData['function_type'];
                $response['total_balance_amount'] = $totalWithdrawAmount;
                $response['invoice_no'] = $invoice_no ?? null;
            }else{
                $response['requestData'] = "";
                $response['status']     =     false;
                $response['message']    =    'Please try again';
                $response['function_type'] = $requestData['function_type'];
                $response['total_balance_amount'] = 0;
                $response['invoice_no'] = '';
            }
        }
        return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error in saveadjustinvoicereport: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while saving the adjusted invoice. Please try again.',
                'requestData' => [],
                'function_type' => $requestData['function_type'] ?? 'add'
            ], 500);
        }
       }
   
    /**
     * Next sequential invoice number (INV-###) for receipt_type invoice rows.
     * Uses a DB transaction; on MySQL/MariaDB also uses GET_LOCK to avoid races when the table is empty.
     */
    private function createInvoiceNumber($invoiceType = 'INV'): string
    {
        $prefix = $invoiceType === 'INV' ? 'INV' : 'INV';

        return DB::transaction(function () use ($prefix) {
            $lockName = 'migrationmanager_invoice_inv_seq';
            $lockHeld = false;
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $acquired = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName]);
                if (! $acquired || (int) $acquired->acquired !== 1) {
                    throw new \RuntimeException('Could not acquire invoice number lock.');
                }
                $lockHeld = true;
            }

            try {
                $max = $this->maxNumericInvoiceSuffix($prefix);
                $next = $max + 1;

                return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            } finally {
                if ($lockHeld) {
                    DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
                }
            }
        });
    }

    /**
     * Highest numeric suffix used on INV-### across header and line-item invoice tables.
     */
    private function maxNumericInvoiceSuffix(string $prefix): int
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $start = strlen($prefix) + 2;
            $max = 0;
            foreach (['account_client_receipts', 'account_all_invoice_receipts'] as $table) {
                foreach (['invoice_no', 'trans_no'] as $col) {
                    $m = (int) (DB::table($table)
                        ->where('receipt_type', 3)
                        ->where($col, 'like', $prefix . '-%')
                        ->selectRaw("MAX(CAST(SUBSTRING(`{$col}`, ?) AS UNSIGNED)) as mx", [$start])
                        ->value('mx') ?? 0);
                    $max = max($max, $m);
                }
            }

            return $max;
        }

        $max = 0;
        $needle = $prefix . '-';
        foreach (['account_client_receipts', 'account_all_invoice_receipts'] as $table) {
            DB::table($table)
                ->where('receipt_type', 3)
                ->where(function ($q) use ($prefix) {
                    $q->where('invoice_no', 'like', "{$prefix}-%")
                        ->orWhere('trans_no', 'like', "{$prefix}-%");
                })
                ->select(['invoice_no', 'trans_no'])
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$max, $needle) {
                    foreach ($rows as $row) {
                        foreach (['invoice_no', 'trans_no'] as $col) {
                            $val = $row->{$col} ?? '';
                            if (is_string($val) && str_starts_with($val, $needle)) {
                                $suffix = substr($val, strlen($needle));
                                if ($suffix !== '' && ctype_digit($suffix)) {
                                    $max = max($max, (int) $suffix);
                                }
                            }
                        }
                    }
                });
        }

        return $max;
    }

    /**
     * Prevent duplicate invoice rows when the same "add" submission is sent twice.
     * Scoped to function_type=add only; edit/adjust/other receipt types are unaffected.
     */
    private function invoiceAddSubmissionCacheKey(int $clientId, string $token): string
    {
        $safeToken = preg_replace('/[^a-zA-Z0-9_-]/', '', $token);

        return 'invoice_add_submission:'.$clientId.':'.$safeToken;
    }

    private function getCachedInvoiceAddResponse(array $requestData): ?array
    {
        $token = trim((string) ($requestData['submission_token'] ?? ''));
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $cached = Cache::get($this->invoiceAddSubmissionCacheKey((int) $requestData['client_id'], $token));

        return is_array($cached) ? $cached : null;
    }

    private function cacheInvoiceAddResponse(array $requestData, array $response): void
    {
        $token = trim((string) ($requestData['submission_token'] ?? ''));
        if ($token === '' || strlen($token) > 128) {
            return;
        }

        if (empty($response['status'])) {
            return;
        }

        Cache::put(
            $this->invoiceAddSubmissionCacheKey((int) $requestData['client_id'], $token),
            $response,
            now()->addMinutes(15)
        );
    }

    private function computeInvoiceAddTotalFromRequest(array $requestData): float
    {
        $total = 0.0;
        $count = count($requestData['trans_date'] ?? []);
        for ($i = 0; $i < $count; $i++) {
            $amount = floatval($requestData['withdraw_amount'][$i] ?? 0);
            if (($requestData['payment_type'][$i] ?? '') === 'Discount') {
                $total -= $amount;
            } else {
                $total += $amount;
            }
        }

        return round($total, 2);
    }

    private function invoiceAddFingerprintFromRequest(array $requestData): string
    {
        $parts = [];
        $count = count($requestData['trans_date'] ?? []);
        for ($i = 0; $i < $count; $i++) {
            $parts[] = implode('|', [
                $requestData['trans_date'][$i] ?? '',
                $requestData['entry_date'][$i] ?? '',
                $requestData['gst_included'][$i] ?? '',
                $requestData['payment_type'][$i] ?? '',
                trim((string) ($requestData['description'][$i] ?? '')),
                number_format((float) ($requestData['withdraw_amount'][$i] ?? 0), 2, '.', ''),
            ]);
        }

        return hash('sha256', implode("\n", $parts));
    }

    private function invoiceAddFingerprintFromReceiptId(int $receiptId, int $clientId): string
    {
        $lines = AccountAllInvoiceReceipt::where('receipt_type', 3)
            ->where('receipt_id', $receiptId)
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        $parts = [];
        foreach ($lines as $line) {
            $parts[] = implode('|', [
                $line->trans_date ?? '',
                $line->entry_date ?? '',
                $line->gst_included ?? '',
                $line->payment_type ?? '',
                trim((string) ($line->description ?? '')),
                number_format((float) ($line->withdraw_amount ?? 0), 2, '.', ''),
            ]);
        }

        return hash('sha256', implode("\n", $parts));
    }

    private function findRecentDuplicateInvoiceAdd(array $requestData): ?object
    {
        if (empty($requestData['trans_date']) || ! is_array($requestData['trans_date'])) {
            return null;
        }

        $clientId = (int) $requestData['client_id'];
        $matterId = $requestData['client_matter_id'] ?? null;
        $saveType = (string) ($requestData['save_type'] ?? '');
        $expectedTotal = $this->computeInvoiceAddTotalFromRequest($requestData);
        $fingerprint = $this->invoiceAddFingerprintFromRequest($requestData);
        $since = now()->subSeconds(90);

        $query = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('client_id', $clientId)
            ->where('created_at', '>=', $since)
            ->where('save_type', $saveType)
            ->where('withdraw_amount', $expectedTotal);

        if ($matterId !== null && $matterId !== '') {
            $query->where('client_matter_id', $matterId);
        } else {
            $query->whereNull('client_matter_id');
        }

        $candidates = $query->orderByDesc('id')->limit(5)->get();

        foreach ($candidates as $row) {
            if ($this->invoiceAddFingerprintFromReceiptId((int) $row->receipt_id, $clientId) === $fingerprint) {
                return $row;
            }
        }

        return null;
    }

    private function buildInvoiceAddJsonResponseFromExisting(object $headerRow, array $requestData): array
    {
        $clientId = (int) $requestData['client_id'];
        $receiptId = (int) $headerRow->receipt_id;
        $lines = AccountAllInvoiceReceipt::where('receipt_type', 3)
            ->where('client_id', $clientId)
            ->where('receipt_id', $receiptId)
            ->orderBy('id')
            ->get();

        $finalArr = [];
        $totalWithdrawAmount = 0.0;
        foreach ($lines as $i => $line) {
            $withdrawAmount = (float) ($line->withdraw_amount ?? 0);
            $finalArr[$i] = [
                'trans_date' => $line->trans_date,
                'entry_date' => $line->entry_date,
                'trans_no' => $line->trans_no ?? $line->invoice_no,
                'gst_included' => $line->gst_included,
                'payment_type' => $line->payment_type,
                'description' => $line->description,
                'withdraw_amount' => $withdrawAmount,
                'balance_amount' => $withdrawAmount,
                'invoice_no' => $line->invoice_no ?? $line->trans_no,
                'save_type' => $line->save_type,
                'receipt_id' => $receiptId,
                'client_matter_id' => $line->client_matter_id,
                'invoice_status' => $line->invoice_status,
                'id' => $line->id,
            ];
            if ($line->payment_type === 'Discount') {
                $totalWithdrawAmount -= $withdrawAmount;
            } else {
                $totalWithdrawAmount += $withdrawAmount;
            }
        }

        $invoiceNo = $headerRow->invoice_no ?? $headerRow->trans_no ?? '';

        return [
            'requestData' => $finalArr,
            'status' => true,
            'message' => 'Invoice added successfully',
            'function_type' => 'add',
            'total_balance_amount' => $totalWithdrawAmount,
            'invoice_no' => $invoiceNo,
        ];
    }
   
       //Save invoice reports
    /**
     * ================================================================
     * INVOICE MANAGEMENT
     * ================================================================
     */

    public function saveinvoicereport(Request $request, $id = NULL)
    {
        try {
            $requestData = $request->all();
            
            // Validate required fields
            if (empty($requestData['client_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client ID is required',
                    'requestData' => [],
                    'function_type' => $requestData['function_type'] ?? 'add'
                ], 400);
            }

            $this->ensureCrmRecordAccess((int) $requestData['client_id']);
            
            if( $requestData['function_type'] == 'add')
        {
            $cachedResponse = $this->getCachedInvoiceAddResponse($requestData);
            if ($cachedResponse !== null) {
                return response()->json($cachedResponse);
            }

            $lastInsertId = null;
            $invoice_no = '';
            $finalArr = [];
            $totalWithdrawAmount = 0;
            if(isset($requestData['trans_date'])){
                $duplicateHeader = $this->findRecentDuplicateInvoiceAdd($requestData);
                if ($duplicateHeader !== null) {
                    $duplicateResponse = $this->buildInvoiceAddJsonResponseFromExisting($duplicateHeader, $requestData);
                    $this->cacheInvoiceAddResponse($requestData, $duplicateResponse);

                    return response()->json($duplicateResponse);
                }

                //Generate unique receipt id
                $is_record_exist = DB::table('account_client_receipts')->select('receipt_id')->where('receipt_type',3)->orderBy('receipt_id', 'desc')->first();
                if(!$is_record_exist){
                    $receipt_id = 1;
                } else {
                    $receipt_id = $is_record_exist->receipt_id +1;
                }
                $finalArr = array();
                $totalWithdrawAmount = 0;
                $invoiceType = 'INV';
                $invoice_no = $this->createInvoiceNumber($invoiceType);
                $invoice_status = 0;

                for($i=0; $i<count($requestData['trans_date']); $i++){
                    // Calculate unit price and withdraw amount based on GST
                    /*$unitPrice = floatval($requestData['withdraw_amount'][$i]);
                    $withdrawAmount = $unitPrice;
                    if ($requestData['gst_included'][$i] == 'Yes') {
                        $withdrawAmount = $unitPrice * 1.10; // Add 10% GST
                    }*/
                    $withdrawAmount = floatval($requestData['withdraw_amount'][$i]);
   
                    $finalArr[$i]['trans_date'] = $requestData['trans_date'][$i];
                    $finalArr[$i]['entry_date'] = $requestData['entry_date'][$i];
                    $finalArr[$i]['trans_no'] = $invoice_no; //$requestData['invoice_no'];
                    $finalArr[$i]['gst_included'] = $requestData['gst_included'][$i];
                    $finalArr[$i]['payment_type'] = $requestData['payment_type'][$i];
                    $finalArr[$i]['description'] = $requestData['description'][$i];
   
                    $finalArr[$i]['withdraw_amount'] = $withdrawAmount; //$requestData['withdraw_amount'][$i];
                    //$finalArr[$i]['unit_price'] = $unitPrice;
                    $finalArr[$i]['balance_amount'] = $withdrawAmount;
   
                    $finalArr[$i]['invoice_no'] = $invoice_no; //$requestData['invoice_no'];
                    $finalArr[$i]['save_type'] = $requestData['save_type'];
                    $finalArr[$i]['receipt_id'] = $receipt_id;
   
                    $finalArr[$i]['client_matter_id'] = $requestData['client_matter_id'] ?? null;
   
                    $finalArr[$i]['invoice_status'] = $invoice_status; //unpaid
   
                    $lastInsertId    = AccountAllInvoiceReceipt::insertGetId([
                        'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                        'client_id' =>  $requestData['client_id'],
                        'client_matter_id' =>  $requestData['client_matter_id'] ?? null,
                        'receipt_id'=>  $receipt_id,
                        'receipt_type' => $requestData['receipt_type'],
                        'trans_date' => $requestData['trans_date'][$i],
                        'entry_date' => $requestData['entry_date'][$i],
                        'gst_included' => $requestData['gst_included'][$i],
                        'payment_type' => $requestData['payment_type'][$i],
                        'trans_no' => !empty($invoice_no) ? $invoice_no : null, //$requestData['invoice_no'],
                        'description' => $requestData['description'][$i],
                        'withdraw_amount' => $withdrawAmount, //$requestData['withdraw_amount'][$i],
                        //'unit_price' => $unitPrice,
                        'invoice_no' => !empty($invoice_no) ? $invoice_no : null, //$requestData['invoice_no'],
                        'save_type' => $requestData['save_type'],
                        'invoice_status' => $invoice_status,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $finalArr[$i]['id'] = $lastInsertId;
   
                    //Save to activity log
                    $subject = 'added invoice.Reference no- '.$invoice_no; //$requestData['invoice_no'];
                    $objs = new ActivitiesLog;
                    $objs->client_id = $requestData['client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->description = '';
                    $objs->subject = $subject;
                    $objs->activity_type = 'financial';
                    $objs->task_status = 0;
                    $objs->pin = 0;
                    $objs->save();
   
                    $amount11 = $withdrawAmount;
                    if ($requestData['payment_type'][$i] == 'Discount') {
                        $totalWithdrawAmount -= $amount11;
                    } else {
                        $totalWithdrawAmount += $amount11;
                    }
                } //end for loop
   
                //main table 'account_client_receipts' entry
                $lastInsertId    = DB::table('account_client_receipts')->insertGetId([
                    'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                    'client_id' =>  $requestData['client_id'],
                    'client_matter_id' =>  $requestData['client_matter_id'] ?? null,
                    'receipt_id'=>  $receipt_id,
                    'receipt_type' => $requestData['receipt_type'],
                    'trans_date' => $requestData['trans_date'][0],
                    'entry_date' => $requestData['entry_date'][0],
                    'gst_included' => $requestData['gst_included'][0],
                    'payment_type' => $requestData['payment_type'][0],
                    'trans_no' => !empty($invoice_no) ? $invoice_no : null,//$requestData['invoice_no'],
                    'description' => $requestData['description'][0],
                    'withdraw_amount' => $totalWithdrawAmount,
                    //'unit_price' => $totalWithdrawAmount,
                    'balance_amount' => $totalWithdrawAmount,
                    'invoice_no' => !empty($invoice_no) ? $invoice_no : null,//$requestData['invoice_no'],
                    'save_type' => $requestData['save_type'],
                    'invoice_status' => $invoice_status,
                    'validate_receipt' => 0,
                    'void_invoice' => 0,
                    'hubdoc_sent' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            if($lastInsertId) {
                $response['requestData']     = $finalArr;
                $response['status']     =     true;
                $response['message']    =    'Invoice added successfully';
                $response['function_type'] = $requestData['function_type'];
                $response['total_balance_amount'] = $totalWithdrawAmount;
                $response['invoice_no'] = $invoice_no;
                $this->cacheInvoiceAddResponse($requestData, $response);
            }else{
                $response['requestData'] = "";
                $response['status']     =     false;
                $response['message']    =    'Please try again';
                $response['function_type'] = $requestData['function_type'];
                $response['total_balance_amount'] = 0;
                $response['invoice_no'] = "";
            }
        }
        else if ($requestData['function_type'] == 'edit') {
            DB::beginTransaction();
            try {
                // Step 1: Check for deleted entries and remove them
                $existingRecords = AccountAllInvoiceReceipt::select('id')
                    ->where('receipt_type', 3)
                    ->where('receipt_id', $requestData['receipt_id'])
                    ->pluck('id')
                    ->toArray();
   
                $requestIds = array_filter($requestData['id'], fn($id) => !empty($id));
                $deletedIds = array_diff($existingRecords, $requestIds);
   
                if (!empty($deletedIds)) {
                    AccountAllInvoiceReceipt::whereIn('id', $deletedIds)->delete();
                }
   
                // Step 2: Get existing invoice number if editing (preserve invoice number)
                $existingInvoice = DB::table('account_client_receipts')
                    ->where('receipt_type', 3)
                    ->where('receipt_id', $requestData['receipt_id'])
                    ->first();
                
                $invoice_no = null;
                if ($existingInvoice && !empty($existingInvoice->invoice_no)) {
                    // Preserve existing invoice number when editing
                    $invoice_no = $existingInvoice->invoice_no;
                } elseif ($existingInvoice && !empty($existingInvoice->trans_no)) {
                    $invoice_no = $existingInvoice->trans_no;
                } else {
                    // Only generate new invoice number if it doesn't exist (new invoice)
                    $invoiceType = 'INV';
                    $invoice_no = $this->createInvoiceNumber($invoiceType);
                    Log::warning('saveinvoicereport edit: generated new invoice number; missing invoice_no and trans_no on existing header row.', [
                        'receipt_id' => $requestData['receipt_id'] ?? null,
                        'client_id' => $requestData['client_id'] ?? null,
                    ]);
                }

                // Step 3: Process all entries (update existing and add new ones)
                $totalWithdrawAmount = 0;
                $lastEntryData = null;
                $processedEntries = []; // To store entries for response
                $currentTimestamp = now(); // Get current timestamp for created_at and updated_at

                foreach ($requestData['trans_date'] as $index => $transDate) {
                    // Calculate unit price and withdraw amount based on GST
                    /*$unitPrice = floatval($requestData['withdraw_amount'][$index]);
                    $withdrawAmount = $unitPrice;
                    if ($requestData['gst_included'][$index] == 'Yes') {
                        $withdrawAmount = $unitPrice * 1.10; // Add 10% GST
                    }*/
                    $withdrawAmount = floatval($requestData['withdraw_amount'][$index]);
   
                    $entryData = [
                        'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                        'client_id' => $requestData['client_id'],
                        'client_matter_id' =>  $requestData['client_matter_id'] ?? null,
                        'receipt_type' => $requestData['receipt_type'],
                        'receipt_id' => $requestData['receipt_id'],
                        'trans_date' => $transDate,
                        'entry_date' => $requestData['entry_date'][$index],
                        'gst_included' => $requestData['gst_included'][$index],
                        'payment_type' => $requestData['payment_type'][$index],
                        'trans_no' => $invoice_no,//$requestData['invoice_no'],
                        'description' => $requestData['description'][$index],
                        'withdraw_amount' => $withdrawAmount,
                        //'unit_price' => $unitPrice,
                        'invoice_no' => $invoice_no, //$requestData['invoice_no'],
                        'save_type' => $requestData['save_type'],
                        'updated_at' => $currentTimestamp, // Add updated_at timestamp
                    ];
                    // Adjust total based on payment type using the GST-adjusted withdraw amount
                    if ($requestData['payment_type'][$index] == 'Discount') {
                        $totalWithdrawAmount -= $withdrawAmount;
                    } else {
                        $totalWithdrawAmount += $withdrawAmount;
                    }
   
                    // Store the last entry data for account_client_receipts
                    $lastEntryData = $entryData;
   
                    // Update or Insert into account_all_invoice_receipts
                    if (!empty($requestData['id'][$index])) {
                        // Update existing entry
                        $entryData['id'] = $requestData['id'][$index];
                        AccountAllInvoiceReceipt::where('id', $requestData['id'][$index])
                            ->update($entryData);
                    } else {
                        // Add new entry with created_at and updated_at
                        $entryData['created_at'] = $currentTimestamp;
                        $entryData['id'] = AccountAllInvoiceReceipt::insertGetId($entryData);
                    }
   
                    // Add to processed entries for response
                    $processedEntries[] = $entryData;
                }
   
                // Step 3: Update or Insert into account_client_receipts with total withdraw_amount and last entry data
                if ($lastEntryData) {
                    $lastEntryData['withdraw_amount'] = $totalWithdrawAmount;
                    $lastEntryData['balance_amount'] = $totalWithdrawAmount;
                    //$lastEntryData['unit_price'] = $totalWithdrawAmount; // Total unit price not applicable here, using total withdraw amount
                    $lastEntryData['updated_at'] = $currentTimestamp; // Add updated_at timestamp
   
                    // Check if a record exists in account_client_receipts for this receipt_id
                    $existingClientReceipt = DB::table('account_client_receipts')
                        ->where('receipt_id', $requestData['receipt_id'])
                        ->where('receipt_type', $requestData['receipt_type'])
                        ->first();
   
                    // Delete cached PDF since invoice was updated
                    if ($existingClientReceipt && !empty($existingClientReceipt->pdf_document_id)) {
                        $pdfDoc = DB::table('documents')->where('id', $existingClientReceipt->pdf_document_id)->first();
                        if ($pdfDoc && !empty($pdfDoc->myfile_key)) {
                            // Delete from S3
                            $client_unique_id = DB::table('admins')->where('id', $existingClientReceipt->client_id)->value('client_id');
                            if ($client_unique_id) {
                                $s3Path = $client_unique_id . '/invoices/' . $pdfDoc->myfile_key;
                                try {
                                    Storage::disk('s3')->delete($s3Path);
                                } catch (\Exception $e) {
                                    Log::warning('Failed to delete PDF from S3: ' . $e->getMessage(), ['path' => $s3Path]);
                                }
                            }
                        }
                        // Delete document record
                        DB::table('documents')->where('id', $existingClientReceipt->pdf_document_id)->delete();
                        // Clear PDF reference in lastEntryData
                        $lastEntryData['pdf_document_id'] = null;
                    }
   
                    if ($existingClientReceipt) {
                        // Update existing record
                        DB::table('account_client_receipts')
                            ->where('receipt_id', $requestData['receipt_id'])
                            ->where('receipt_type', $requestData['receipt_type'])
                            ->update($lastEntryData);
                    } else {
                        // Insert new record with created_at and updated_at
                        $lastEntryData['created_at'] = $currentTimestamp;
                        $lastEntryData['validate_receipt'] = 0;
                        $lastEntryData['void_invoice'] = 0;
                        $lastEntryData['invoice_status'] = $lastEntryData['invoice_status'] ?? 0;
                        $lastEntryData['save_type'] = isset($lastEntryData['save_type']) ? $lastEntryData['save_type'] : $requestData['save_type'];
                        $lastEntryData['hubdoc_sent'] = 0;
                        DB::table('account_client_receipts')->insert($lastEntryData);
                    }
                }
   
                DB::commit();
   
                $response = [
                    'requestData' => $processedEntries,
                    'status' => true,
                    'message' => 'Invoice updated successfully',
                    'function_type' => $requestData['function_type'],
                    'invoice_no' => $invoice_no,
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                $response = [
                    'requestData' => [],
                    'status' => false,
                    'message' => 'Please try again: ' . $e->getMessage(),
                    'function_type' => $requestData['function_type'],
                    'invoice_no' => "",
                ];
            }
        }
        return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error in saveinvoicereport: ' . $e->getMessage(), [
                'request_data' => $request->except(['document_upload']),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while saving the invoice. Please try again.',
                'requestData' => [],
                'function_type' => $requestData['function_type'] ?? 'add'
            ], 500);
        }
       }
   
    public function isAnyInvoiceNoExistInDB(Request $request)
    {
        $requestData         =     $request->all();
        if (!empty($requestData['client_id'])) {
            $this->ensureCrmRecordAccess((int) $requestData['client_id']);
        }
        $record_count = DB::table('account_client_receipts')->where('client_id',$requestData['client_id'])->where('invoice_no','!=' ,'')->count();
        if($record_count) {
            $response['record_count']     = $record_count;
            $response['status']     =     true;
            $response['message']    =    'Record is exist';
        }else{
            $response['record_count']     = $record_count;
            $response['status']     =     false;
            $response['message']    =    'Record is not exist.Please try again';
        }
        return response()->json($response);
       }
   
    public function listOfInvoice(Request $request)
    {
        $requestData         =     $request->all();
        if (!empty($requestData['client_id'])) {
            $this->ensureCrmRecordAccess((int) $requestData['client_id']);
        }
        $response            =     [];
        // Get only non-voided invoices that are unpaid or partially paid
        $record_get = DB::table('account_client_receipts')
            ->select('invoice_no', 'invoice_status', 'balance_amount')
            ->where('client_matter_id',$requestData['selectedMatter'])
            ->where('client_id',$requestData['client_id'])
            ->where('receipt_type',3)
            ->where('save_type','final')
            ->where(function($query) {
                // Only include unpaid (0) or partially paid (2) invoices
                $query->where('invoice_status', 0)
                      ->orWhere('invoice_status', 2);
            })
            ->where(function($query) {
                // Exclude voided invoices
                $query->whereNull('void_invoice')
                      ->orWhere('void_invoice', 0);
            })
            ->distinct()
            ->get();
        if(!empty($record_get)) {
            $str = '<option value="">Select</option>';
            foreach($record_get as $key=>$val) {
                // Show balance amount in dropdown for clarity
                $balance = number_format($val->balance_amount, 2);
                $str .=  '<option value="'.$val->invoice_no.'">'.$val->invoice_no.' (Balance: $'.$balance.')</option>';
            }
            $response['record_get']     = $str;
            $response['status']     =     true;
            $response['message']    =    'Record is exist';
        }else{
            $response['record_get']     = '<option value="">No unpaid invoices available</option>';
            $response['status']     =     false;
            $response['message']    =    'No unpaid invoices found';
        }
        return response()->json($response);
       }
   
    public function clientLedgerBalanceAmount(Request $request)
    {
        $requestData         =     $request->all();
        if (!empty($requestData['client_id'])) {
            $this->ensureCrmRecordAccess((int) $requestData['client_id']);
        }
        $response            =     [];
        $latest_balance = DB::table('account_client_receipts')
            ->where('client_matter_id', $requestData['selectedMatter'])
            ->where('client_id', $requestData['client_id'])
            ->where('receipt_type', 1)
            ->where(function($query) {
                $query->whereNull('void_fee_transfer')
                      ->orWhere('void_fee_transfer', 0);
            })
            ->orderBy('id', 'desc')
            ->value('balance_amount');
        if( is_numeric($latest_balance) ) {
            $response['record_get'] = $latest_balance;
            $response['status']     =     true;
            $response['message']    =    'Record is exist';
        } else {
            $latest_balance = 0;
            $response['record_get'] = 0;
            $response['status']     =     false;
            $response['message']    =    'Record is not exist.Please try again';
        }
        return response()->json($response);
       }
   
    public function getInfoByReceiptId(Request $request)
    {
        $requestData = $request->all();
        $response    = [];
        $receiptid = $requestData['receiptid'];
        if ($receiptid !== null && $receiptid !== '') {
            $cid = DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->where('receipt_id', $receiptid)
                ->value('client_id');
            if ($cid) {
                $this->ensureCrmRecordAccess((int) $cid);
            }
        }
        $record_get = array();
        $record_get_parent = array();
   
        $record_get_parent = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('receipt_id', $receiptid)
            ->get();
   
        $record_get  = AccountAllInvoiceReceipt::where('receipt_type', 3)
            ->where('receipt_id', $receiptid)
            ->get();
   
        if (!empty($record_get)) {
            $response['record_get'] = $record_get;
            $response['record_get_parent'] = $record_get_parent;
            $response['status'] = true;
            $response['message'] = 'Record is exist';
   
            $last_record_id = DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->max('id');
            $response['last_record_id'] = $last_record_id;
        } else {
            $response['record_get'] = $record_get;
            $response['record_get_parent'] = $record_get_parent;
            $response['status'] = false;
            $response['message'] = 'Record is not exist.Please try again';
            $response['last_record_id'] = 0;
        }
        return response()->json($response);
       }
   
    public function getTopReceiptValInDB(Request $request)
    {
        $requestData =     $request->all();
        $response    =     [];
        $receipt_type = $requestData['type'];
        $record_count = DB::table('account_client_receipts')->where('receipt_type',$receipt_type)->max('id');
        if($record_count) {
            if($receipt_type == 3){ //type = invoice
                $max_receipt_id = DB::table('account_client_receipts')->where('receipt_type',$receipt_type)->max('receipt_id');
                $response['max_receipt_id']     = $max_receipt_id;
            } else {
                $response['max_receipt_id']     = "";
            }
            $response['receipt_type']     = $receipt_type;
            $response['record_count']     = $record_count;
            $response['status']     =     true;
            $response['message']    =    'Record is exist';
        }else{
            $response['receipt_type']     = $receipt_type;
            $response['record_count']     = $record_count;
            $response['max_receipt_id']     = "";
            $response['status']     =     false;
            $response['message']    =    'Record is not exist.Please try again';
        }
        return response()->json($response);
       }
   
    public function getTopInvoiceNoFromDB(Request $request)
    {
        $requestData =     $request->all();
        $response    =     [];
        $receipt_type = $requestData['type'];
   
        // Preview only (not reserved). Uses same sequence source as createInvoiceNumber.
        $prefix = 'INV';
        $max = $this->maxNumericInvoiceSuffix($prefix);
        $nextNumber = $max + 1;
        $formattedNumber = str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
        $invoice_no = $prefix . '-' . $formattedNumber;
   
        $response['max_receipt_id'] = $invoice_no;
        $response['status']     =     true;
        $response['message']    =    'Record is exist';
        return response()->json($response);
       }
  // NEW SIMPLIFIED saveofficereport function - Only handles office receipts (receipt_type=2)
  public function saveofficereport(Request $request, $id = NULL)
  {
      try {
          $requestData = $request->all();
          $response = [];
          
          // Validate required fields
          if (empty($requestData['client_id'])) {
              return response()->json([
                  'status' => false,
                  'message' => 'Client ID is required',
                  'requestData' => [],
                  'awsUrl' => ""
              ], 400);
          }

          $this->ensureCrmRecordAccess((int) $requestData['client_id']);

          // Handle document upload
          $insertedDocId = null;
          $doc_saved = false;
          $client_unique_id = "";
          $awsUrl = "";
          $doctype = isset($request->doctype) ? $request->doctype : '';

      if ($request->hasfile('document_upload')) {
          $files = is_array($request->file('document_upload')) ? $request->file('document_upload') : [$request->file('document_upload')];

          $client_info = \App\Models\Admin::select('client_id')->where('id', $requestData['client_id'])->first();
          $client_unique_id = !empty($client_info) ? $client_info->client_id : "";

          foreach ($files as $file) {
           $size = $file->getSize();
           $fileName = $file->getClientOriginalName();
           $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
           $fileExtension = $file->getClientOriginalExtension();
           $name = time() . $file->getClientOriginalName();
           $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
           Storage::disk('s3')->put($filePath, file_get_contents($file));

           $obj = new \App\Models\Document;
           $obj->file_name = $nameWithoutExtension;
           $obj->filetype = $fileExtension;
           $obj->user_id = Auth::user()->id;
           $obj->myfile = $this->s3PublicUrl($filePath);
           $obj->myfile_key = $name;
           $obj->client_id = $requestData['client_id'];
           $obj->type = $request->type;
           $obj->file_size = $size;
           $obj->doc_type = $doctype;
           $doc_saved = $obj->save();
           $insertedDocId = $obj->id;
          }
      }

      // Get save type (draft or final)
      $saveType = isset($requestData['save_type']) ? $requestData['save_type'] : 'final';

      // Initialize variables outside conditional block to ensure they're always set
      $finalArr = [];
      $saved = false;
      $processedInvoices = []; // Track invoices that need status updates

      // Validate trans_date exists and is an array with at least one entry
      if (!isset($requestData['trans_date']) || !is_array($requestData['trans_date']) || empty($requestData['trans_date'])) {
          return response()->json([
              'status' => false,
              'message' => 'Transaction date is required. Please add at least one receipt entry.',
              'requestData' => [],
              'awsUrl' => ""
          ], 400);
      }

      // Handle office receipt processing (receipt_type=2 only)
      $is_record_exist = DB::table('account_client_receipts')->select('receipt_id')->where('receipt_type', 2)->orderBy('receipt_id', 'desc')->first();
      $receipt_id = !$is_record_exist ? 1 : $is_record_exist->receipt_id + 1;

      // Process each transaction individually (no invoice grouping)
      $savedIds = []; // Track all saved receipt IDs
      for ($i = 0; $i < count($requestData['trans_date']); $i++) {
          // Validate required fields for this transaction
          if (empty($requestData['trans_date'][$i]) || empty($requestData['deposit_amount'][$i])) {
              Log::warning('Skipping office receipt entry due to missing required fields', [
                  'index' => $i,
                  'trans_date' => $requestData['trans_date'][$i] ?? 'missing',
                  'deposit_amount' => $requestData['deposit_amount'][$i] ?? 'missing'
              ]);
              continue; // Skip this entry but continue with others
          }

          $trans_no = $this->generateTransNo();
          $invoiceNo = isset($requestData['invoice_no'][$i]) && $requestData['invoice_no'][$i] !== '' ? $requestData['invoice_no'][$i] : null;
          $pmOffice = trim((string) ($requestData['payment_method'][$i] ?? ''));
          $principalOffice = floatval($requestData['deposit_amount'][$i] ?? 0);
          $surchargeOffice = 0.0;
          if ($pmOffice === 'EFTPOS' && ! empty($requestData['eftpos_surcharge_amount']) && is_array($requestData['eftpos_surcharge_amount'])) {
              $surchargeOffice = round(max(0, floatval($requestData['eftpos_surcharge_amount'][$i] ?? 0)), 2);
          }
          $totalDepositOffice = round($principalOffice + $surchargeOffice, 2);

          try {
              $insertedId = DB::table('account_client_receipts')->insertGetId([
                  'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
                  'client_id' => $requestData['client_id'],
                  'client_matter_id' => $requestData['client_matter_id'] ?? null,
                  'receipt_id' => $receipt_id,
                  'receipt_type' => 2, // Only office receipts
                  'trans_date' => $requestData['trans_date'][$i],
                  'entry_date' => $requestData['entry_date'][$i] ?? $requestData['trans_date'][$i],
                  'trans_no' => $trans_no,
                  'invoice_no' => $invoiceNo,
                  'payment_method' => $pmOffice,
                  'description' => $requestData['description'][$i] ?? '',
                  'deposit_amount' => $totalDepositOffice,
                  'eftpos_surcharge_amount' => $surchargeOffice > 0 ? $surchargeOffice : null,
                  'uploaded_doc_id' => $insertedDocId,
                  'save_type' => $saveType, // Track if draft or final
                  'validate_receipt' => 0,
                  'void_invoice' => 0,
                  'invoice_status' => 0,
                  'hubdoc_sent' => 0,
                  'created_at' => now(),
                  'updated_at' => now(),
              ]);

              // Mark as saved if we got an ID back
              if ($insertedId) {
                  $saved = true;
                  $savedIds[] = $insertedId;

                  $finalArr[] = [
                      'trans_date' => $requestData['trans_date'][$i],
                      'entry_date' => $requestData['entry_date'][$i] ?? $requestData['trans_date'][$i],
                      'trans_no' => $trans_no,
                      'invoice_no' => $invoiceNo,
                      'payment_method' => $pmOffice,
                      'description' => $requestData['description'][$i] ?? '',
                      'deposit_amount' => $totalDepositOffice,
                      'eftpos_surcharge_amount' => $surchargeOffice > 0 ? $surchargeOffice : null,
                  ];

                  // Track invoices that need status updates (only for final receipts with invoice_no)
                  if ($saveType == 'final' && !empty($invoiceNo)) {
                      if (!isset($processedInvoices[$invoiceNo])) {
                          $processedInvoices[$invoiceNo] = [];
                      }
                      $processedInvoices[$invoiceNo][] = [
                          'receipt_id' => $insertedId,
                          'amount' => $totalDepositOffice
                      ];
                  }
              }
          } catch (\Exception $e) {
              Log::error('Error inserting office receipt entry', [
                  'index' => $i,
                  'error' => $e->getMessage(),
                  'trace' => $e->getTraceAsString()
              ]);
              // Continue processing other entries even if one fails
          }
      }

      // If no receipts were saved, return error
      if (!$saved || empty($finalArr)) {
          return response()->json([
              'status' => false,
              'message' => 'Failed to save office receipt. Please check that all required fields are filled and try again.',
              'requestData' => [],
              'awsUrl' => ""
          ], 400);
      }

      // Process invoice matching for all affected invoices (only for final receipts)
      $invoicePaymentSync = app(InvoicePaymentSyncService::class);
      if ($saveType == 'final' && !empty($processedInvoices)) {
              foreach ($processedInvoices as $invoiceNo => $receipts) {
                  try {
                      // Check if invoice is voided - skip if voided
                      $invoiceRow = DB::table('account_client_receipts')
                          ->where('receipt_type', 3)
                          ->where('invoice_no', $invoiceNo)
                          ->where('client_id', $requestData['client_id'])
                          ->first();

                      if ($invoiceRow) {
                          if (!empty($invoiceRow->void_invoice) && $invoiceRow->void_invoice == 1) {
                              Log::warning('Attempted to match receipt to voided invoice', [
                                  'invoice_no' => $invoiceNo,
                                  'client_id' => $requestData['client_id']
                              ]);
                              continue;
                          }

                          $synced = $invoicePaymentSync->persistPaymentState($requestData['client_id'], $invoiceNo);
                          if ($synced !== null) {
                              Log::info('Invoice status updated after office receipt creation', [
                                  'invoice_no' => $invoiceNo,
                                  'total_paid' => $synced['total_paid'],
                                  'new_balance' => $synced['new_balance'],
                                  'new_status' => $synced['invoice_status'],
                                  'client_id' => $requestData['client_id']
                              ]);
                          }
                      } else {
                          Log::warning('Invoice not found for receipt matching', [
                              'invoice_no' => $invoiceNo,
                              'client_id' => $requestData['client_id']
                          ]);
                      }
                  } catch (\Exception $e) {
                      Log::error('Error updating invoice status in saveofficereport', [
                          'invoice_no' => $invoiceNo,
                          'error' => $e->getMessage(),
                          'trace' => $e->getTraceAsString()
                      ]);
                      // Continue processing other invoices even if one fails
                  }
              }
          }

      // Log activity
      if ($saved && !empty($finalArr)) {
           // Get the last transaction details for logging
           $lastEntry = end($finalArr);
           $lastTransDate = $lastEntry['trans_date'] ?? '';
           $lastDeposit = $lastEntry['deposit_amount'] ?? 0;
           $lastTransNo = $lastEntry['trans_no'] ?? $trans_no;
           
           $subject = $doc_saved ? 'added office receipt with its document. Reference no- ' . $lastTransNo : 'added office receipt. Reference no- ' . $lastTransNo;
           $description = "Receipt Date: {$lastTransDate}, Amount: \${$lastDeposit}";
           if ($request->type == 'client') {
               $objs = new \App\Models\ActivitiesLog;
               $objs->client_id = $requestData['client_id'];
               $objs->created_by = Auth::user()->id;
               $objs->description = $description;
               $objs->subject = $subject;
               $objs->activity_type = 'financial';
               $objs->task_status = 0;
               $objs->pin = 0;
               $objs->save();
           }
      }

      // Prepare response
      if ($saved) {
          $response['status'] = true;
          $response['requestData'] = $finalArr;
          $response['message'] = $doc_saved ? 'Office receipt with document added successfully' : 'Office receipt added successfully';
          if ($doc_saved && isset($name) && !empty($name)) {
           $url = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
           $awsUrl = $url . $client_unique_id . '/' . $doctype . '/' . $name;
           $response['awsUrl'] = $awsUrl;
          } else {
           $response['awsUrl'] = "";
          }

          // Add invoice data to response for frontend (if invoice was matched)
          if ($saveType == 'final' && !empty($finalArr)) {
              // Get the first receipt with an invoice_no (for backward compatibility)
              $firstReceiptWithInvoice = null;
              foreach ($finalArr as $receipt) {
                  if (!empty($receipt['invoice_no'])) {
                      $firstReceiptWithInvoice = $receipt;
                      break;
                  }
              }

              if ($firstReceiptWithInvoice) {
                  $invoiceNo = $firstReceiptWithInvoice['invoice_no'];
                  $invoice = DB::table('account_client_receipts')
                      ->where('receipt_type', 3)
                      ->where('invoice_no', $invoiceNo)
                      ->where('client_id', $requestData['client_id'])
                      ->first();

                  if ($invoice) {
                      $response['invoice_no'] = $invoiceNo;
                      $response['invoice_balance'] = floatval($invoice->balance_amount ?? $invoice->withdraw_amount);
                      $response['invoice_status'] = $invoice->invoice_status ?? 0;
                  }
              }
          }
      } else {
          $response['status'] = false;
          $response['requestData'] = [];
          $response['awsUrl'] = "";
          $response['message'] = 'Please try again';
      }

      return response()->json($response, 200);
      } catch (\Exception $e) {
          Log::error('Error in saveofficereport: ' . $e->getMessage(), [
              'request_data' => $request->except(['document_upload']),
              'trace' => $e->getTraceAsString()
          ]);
          
          return response()->json([
              'status' => false,
              'message' => 'An error occurred while saving the office receipt. Please try again.',
              'requestData' => [],
              'awsUrl' => ""
          ], 500);
      }
  }

  // Helper methods
  private function generateTransNo()
  {
      $latestTrans = DB::table('account_client_receipts')
          ->select('trans_no')
          ->where('receipt_type', 2)
          ->orderBy('id', 'desc')
          ->first();

      if (!$latestTrans) {
          $nextNumber = 1;
      } else {
          $lastTransNo = explode('-', $latestTrans->trans_no);
          $lastNumber = isset($lastTransNo[1]) ? (int)$lastTransNo[1] : 0;
          $nextNumber = $lastNumber + 1;
      }

      return 'REC-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
  }

  // Update Office Receipt
  public function updateOfficeReceipt(Request $request)
  {
      $requestData = $request->all();
      if (!empty($requestData['client_id'])) {
          $this->ensureCrmRecordAccess((int) $requestData['client_id']);
      }
      $id = $request->input('id');
      $saveType = $request->input('save_type', 'final');
      
      // Handle document upload if new file is provided
      $insertedDocId = null;
      $doctype = isset($request->doctype) ? $request->doctype : '';
      
      if ($request->hasfile('document_upload')) {
          $files = is_array($request->file('document_upload')) ? $request->file('document_upload') : [$request->file('document_upload')];
          
          $client_info = \App\Models\Admin::select('client_id')->where('id', $requestData['client_id'])->first();
          $client_unique_id = !empty($client_info) ? $client_info->client_id : "";
          
          foreach ($files as $file) {
           $size = $file->getSize();
           $fileName = $file->getClientOriginalName();
           $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
           $fileExtension = $file->getClientOriginalExtension();
           $name = time() . $file->getClientOriginalName();
           $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
           Storage::disk('s3')->put($filePath, file_get_contents($file));
           
           $obj = new \App\Models\Document;
           $obj->file_name = $nameWithoutExtension;
           $obj->filetype = $fileExtension;
           $obj->user_id = Auth::user()->id;
           $obj->myfile = $this->s3PublicUrl($filePath);
           $obj->myfile_key = $name;
           $obj->client_id = $requestData['client_id'];
           $obj->type = $request->type;
           $obj->file_size = $size;
           $obj->doc_type = $doctype;
           $obj->save();
           $insertedDocId = $obj->id;
          }
      }
      
      // Prepare update data - PARTIAL UPDATE SUPPORT (only update provided fields)
      $updateData = [];
      
      // Only add fields that are provided in the request
      if ($request->has('trans_date')) {
          $updateData['trans_date'] = $request->input('trans_date');
      }
      if ($request->has('entry_date')) {
          $updateData['entry_date'] = $request->input('entry_date');
      }
      if ($request->has('payment_method')) {
          $updateData['payment_method'] = $request->input('payment_method');
      }
      if ($request->has('description')) {
          $updateData['description'] = $request->input('description');
      }
      // Get the original receipt data BEFORE updating (needed for overpayment check and surcharge calc)
      $originalReceipt = DB::table('account_client_receipts')->where('id', $id)->first();

      if ($request->has('deposit_amount') && $originalReceipt) {
          $principal = floatval($request->input('deposit_amount'));
          $pm = trim((string) ($request->input('payment_method', $originalReceipt->payment_method ?? '')));
          $surcharge = ($pm === 'EFTPOS') ? max(0, floatval($request->input('eftpos_surcharge_amount', 0))) : 0.0;
          $updateData['deposit_amount'] = round($principal + $surcharge, 2);
          $updateData['eftpos_surcharge_amount'] = $surcharge > 0 ? round($surcharge, 2) : null;
      } elseif ($request->has('payment_method') && $originalReceipt) {
          $pm = trim((string) $request->input('payment_method'));
          $total = floatval($originalReceipt->deposit_amount);
          $oldSur = floatval($originalReceipt->eftpos_surcharge_amount ?? 0);
          $principal = max(0, round($total - $oldSur, 2));
          $surcharge = ($pm === 'EFTPOS') ? $oldSur : 0.0;
          $updateData['deposit_amount'] = round($principal + $surcharge, 2);
          $updateData['eftpos_surcharge_amount'] = $surcharge > 0 ? round($surcharge, 2) : null;
      }
      if ($request->has('invoice_no')) {
          $updateData['invoice_no'] = $request->input('invoice_no', '');
      }
      
      // Always update these fields
      $updateData['save_type'] = $saveType;
      $updateData['updated_at'] = now();
      
      // Only update document if new one was uploaded
      if ($insertedDocId !== null) {
          $updateData['uploaded_doc_id'] = $insertedDocId;
      }
      
      // Update the record
      $updated = DB::table('account_client_receipts')
          ->where('id', $id)
          ->where('receipt_type', 2)
          ->update($updateData);
      
      if ($updated) {
          // Get the updated receipt
          $receipt = DB::table('account_client_receipts')->where('id', $id)->first();
          
          // If invoice_no was updated, recalculate invoice payment status
          if ($request->has('invoice_no') && !empty($request->input('invoice_no'))) {
              $invoiceNo = $request->input('invoice_no');
              
              $invoicePaymentSync = app(InvoicePaymentSyncService::class);
              $isOverpayment = false;

              if ($invoicePaymentSync->invoiceLinesExist($receipt->client_id, $invoiceNo)) {
                  $receiptAmount = floatval($receipt->deposit_amount);
                  $invoiceBalance = $invoicePaymentSync->outstandingExcludingOfficeReceiptId($receipt->client_id, $invoiceNo, (int) $id);
                  
                  // Check if receipt amount exceeds invoice balance (overpayment)
                  $excessAmount = $receiptAmount - $invoiceBalance;
                  $isOverpayment = $excessAmount > 0.01; // Allow for small rounding differences
                  
                  if ($isOverpayment) {
                      // Split the receipt: allocate only invoice amount, create residual receipt
                      $amountToAllocate = $invoiceBalance;
                      
                      // Update original receipt to allocate only the invoice amount
                      DB::table('account_client_receipts')
                          ->where('id', $id)
                          ->update([
                              'deposit_amount' => $amountToAllocate,
                              'updated_at' => now(),
                          ]);
                      
                      // Create residual receipt for the excess amount
                      $residualTransNo = $this->generateTransNo();
                      $residualReceiptId = DB::table('account_client_receipts')->insertGetId([
                          'user_id' => $originalReceipt->user_id,
                          'client_id' => $originalReceipt->client_id,
                          'client_matter_id' => $originalReceipt->client_matter_id,
                          'receipt_id' => $originalReceipt->receipt_id,
                          'receipt_type' => 2, // Office receipt
                          'trans_date' => $originalReceipt->trans_date,
                          'entry_date' => $originalReceipt->entry_date ?? $originalReceipt->trans_date,
                          'trans_no' => $residualTransNo,
                          'invoice_no' => null, // Unallocated
                          'payment_method' => $originalReceipt->payment_method,
                          'description' => 'Residual from ' . $originalReceipt->trans_no . ' - Applied to ' . $invoiceNo,
                          'deposit_amount' => $excessAmount,
                          'withdraw_amount' => 0,
                          'balance_amount' => 0,
                          'save_type' => $saveType,
                          'extra_amount_receipt' => 'residual',
                          'validate_receipt' => 0,
                          'void_invoice' => 0,
                          'invoice_status' => 0,
                          'hubdoc_sent' => 0,
                          'created_at' => now(),
                          'updated_at' => now(),
                      ]);
                      
                      Log::info('Residual receipt created', [
                          'original_receipt_id' => $id,
                          'original_receipt_no' => $originalReceipt->trans_no,
                          'residual_receipt_id' => $residualReceiptId,
                          'residual_receipt_no' => $residualTransNo,
                          'excess_amount' => $excessAmount,
                          'allocated_amount' => $amountToAllocate,
                          'invoice_no' => $invoiceNo
                      ]);
                      
                      // Refresh receipt data after update
                      $receipt = DB::table('account_client_receipts')->where('id', $id)->first();
                  }
                  
                  $synced = $invoicePaymentSync->persistPaymentState((int) $receipt->client_id, $invoiceNo);
                  
                  Log::info('Invoice status updated after receipt allocation', [
                      'invoice_no' => $invoiceNo,
                      'total_paid' => $synced !== null ? $synced['total_paid'] : null,
                      'new_balance' => $synced !== null ? $synced['new_balance'] : null,
                      'new_status' => $synced !== null ? $synced['invoice_status'] : null,
                      'residual_created' => $isOverpayment,
                  ]);
              }
          }
          
          // Delete cached PDF since office receipt was updated
          if ($receipt && !empty($receipt->pdf_document_id)) {
           $pdfDoc = DB::table('documents')->where('id', $receipt->pdf_document_id)->first();
           if ($pdfDoc && !empty($pdfDoc->myfile_key)) {
               // Delete from S3
               $client_unique_id = DB::table('admins')->where('id', $receipt->client_id)->value('client_id');
               if ($client_unique_id) {
                   $s3Path = $client_unique_id . '/office_receipts/' . $pdfDoc->myfile_key;
                   try {
                       Storage::disk('s3')->delete($s3Path);
                   } catch (\Exception $e) {
                       Log::warning('Failed to delete PDF from S3: ' . $e->getMessage(), ['path' => $s3Path]);
                   }
               }
           }
           // Delete document record
           DB::table('documents')->where('id', $receipt->pdf_document_id)->delete();
           // Clear PDF reference
           DB::table('account_client_receipts')
               ->where('id', $id)
               ->update(['pdf_document_id' => null]);
          }
          
          // Log activity
          $userName = Auth::user()->first_name . ' ' . Auth::user()->last_name;
          $formattedAmount = '$' . number_format(floatval($receipt->deposit_amount), 2);
          $transDate = date('d/m/Y', strtotime($receipt->trans_date));
          $saveTypeText = $saveType == 'draft' ? ' (Draft)' : '';
          
          $subject = "Office Receipt Updated - {$formattedAmount} (Ref: {$receipt->trans_no}){$saveTypeText}";
          
          $description = "<div class='activity-detail'>";
          $description .= "<p><strong>{$userName}</strong> updated an office receipt:</p>";
          $description .= "<ul>";
          $description .= "<li><strong>Reference No:</strong> {$receipt->trans_no}</li>";
          $description .= "<li><strong>Amount:</strong> {$formattedAmount}</li>";
          $description .= "<li><strong>Transaction Date:</strong> {$transDate}</li>";
          $description .= "<li><strong>Payment Method:</strong> {$receipt->payment_method}</li>";
          if (!empty($receipt->description)) {
              $description .= "<li><strong>Description:</strong> " . htmlspecialchars($receipt->description) . "</li>";
          }
          if (!empty($receipt->invoice_no)) {
              $description .= "<li><strong>Invoice No:</strong> {$receipt->invoice_no}</li>";
          }
          if ($insertedDocId !== null) {
              $description .= "<li><strong>Document:</strong> Updated</li>";
          }
          $description .= "<li><strong>Status:</strong> " . ($saveType == 'draft' ? 'Draft' : 'Finalized') . "</li>";
          $description .= "</ul>";
          $description .= "</div>";
          
          $objs = new \App\Models\ActivitiesLog;
          $objs->client_id = $requestData['client_id'];
          $objs->created_by = Auth::user()->id;
          $objs->description = $description;
          $objs->subject = $subject;
          $objs->activity_type = 'financial';
          $objs->task_status = 0;
          $objs->pin = 0;
          $objs->save();
          
          return response()->json([
           'status' => true,
           'message' => $saveType == 'draft' ? 'Office receipt draft saved successfully' : 'Office receipt finalized successfully',
          ], 200);
      }
      
      return response()->json([
          'status' => false,
          'message' => 'Failed to update office receipt',
      ], 500);
  }
  
  // Get invoices by matter for dropdown
  public function getInvoicesByMatter(Request $request)
  {
      try {
          $matterId = $request->input('client_matter_id');
          $clientId = $request->input('client_id');
          
          Log::info('getInvoicesByMatter called', [
              'client_id' => $clientId,
              'matter_id' => $matterId
          ]);
          
          if (empty($clientId)) {
              return response()->json([
                  'status' => false,
                  'message' => 'Client ID is required',
                  'invoices' => [],
              ], 400);
          }

          $this->ensureCrmRecordAccess((int) $clientId);
          
          $baseQuery = DB::table('account_client_receipts')
              ->select(
                  DB::raw('invoice_no as trans_no'),
                  DB::raw('SUM(COALESCE(balance_amount, 0)) as balance_amount'),
                  DB::raw('MAX(COALESCE(invoice_status, 0)) as invoice_status'),
                  DB::raw('MAX(description) as description'),
                  DB::raw('MAX(trans_date) as latest_trans_date')
              )
              ->where('client_id', $clientId)
              ->where('receipt_type', 3)
              ->where(function ($q) {
                  $q->whereNull('save_type')
                      ->orWhereRaw('LOWER(TRIM(COALESCE(save_type, \'\'))) <> ?', ['draft']);
              })
              ->where(function ($query) {
                  $query->whereIn('invoice_status', [0, 2])
                        ->orWhereNull('invoice_status');
              })
              ->whereNotNull('invoice_no')
              ->where('invoice_no', '!=', '')
              ->groupBy('invoice_no');

          if (!empty($matterId)) {
              $baseQuery->where('client_matter_id', $matterId);
          } else {
              $baseQuery->whereNull('client_matter_id');
          }

          $invoices = (clone $baseQuery)
              ->orderByDesc('latest_trans_date')
              ->get();

          Log::info('First query returned ' . $invoices->count() . ' invoices');

          // Fallback: if no invoices are found for the matter, fetch all unpaid/partial invoices for the client
          if ($invoices->isEmpty() && !empty($matterId)) {
              Log::info('No invoices for matter, fetching all client invoices');
              $invoices = DB::table('account_client_receipts')
                  ->select(
                      DB::raw('invoice_no as trans_no'),
                      DB::raw('SUM(COALESCE(balance_amount, 0)) as balance_amount'),
                      DB::raw('MAX(COALESCE(invoice_status, 0)) as invoice_status'),
                      DB::raw('MAX(description) as description'),
                      DB::raw('MAX(trans_date) as latest_trans_date')
                  )
                  ->where('client_id', $clientId)
                  ->where('receipt_type', 3)
                  ->where(function ($q) {
                      $q->whereNull('save_type')
                          ->orWhereRaw('LOWER(TRIM(COALESCE(save_type, \'\'))) <> ?', ['draft']);
                  })
                  ->where(function ($query) {
                      $query->whereIn('invoice_status', [0, 2])
                            ->orWhereNull('invoice_status');
                  })
                  ->whereNotNull('invoice_no')
                  ->where('invoice_no', '!=', '')
                  ->groupBy('invoice_no')
                  ->orderByDesc('latest_trans_date')
                  ->get();
                  
              Log::info('Fallback query returned ' . $invoices->count() . ' invoices');
          }
          
          // Add status text
          $invoices = $invoices->map(function($invoice) {
              $statusMap = ['0' => 'Unpaid', '1' => 'Paid', '2' => 'Partial', '3' => 'Void'];
              $invoice->status = $statusMap[$invoice->invoice_status] ?? 'Unknown';
              return $invoice;
          });
          
          return response()->json([
              'status' => true,
              'invoices' => $invoices,
              'count' => $invoices->count(),
          ], 200);
          
      } catch (\Exception $e) {
          Log::error('getInvoicesByMatter error: ' . $e->getMessage(), [
              'trace' => $e->getTraceAsString()
          ]);
          
          return response()->json([
              'status' => false,
              'message' => 'Database error: ' . $e->getMessage(),
              'invoices' => [],
          ], 500);
      }
  }

  // Update Client Fund Ledger Entry (for allocating deposits to invoices)
  public function updateClientFundLedger(Request $request)
  {
      try {
          $id = $request->input('id');
          $invoiceNo = $request->input('invoice_no');
          $clientId = $request->input('client_id');
          
          Log::info('updateClientFundLedger called', [
              'id' => $id,
              'invoice_no' => $invoiceNo,
              'client_id' => $clientId
          ]);

          if (!empty($clientId)) {
              $this->ensureCrmRecordAccess((int) $clientId);
          }
          
          // Get the deposit ledger entry
          $depositEntry = DB::table('account_client_receipts')
              ->where('id', $id)
              ->where('receipt_type', 1)
              ->where('client_id', $clientId)
              ->first();
          
          if (!$depositEntry) {
              return response()->json([
                  'status' => false,
                  'message' => 'Deposit entry not found',
              ], 404);
          }
          
          // Check if this is a re-allocation (deposit already has an invoice_no)
          $oldInvoiceNo = $depositEntry->invoice_no;
          $isReallocation = !empty($oldInvoiceNo) && $oldInvoiceNo != $invoiceNo;
          
          if ($isReallocation) {
              // Void the old fee transfer linked to the old invoice
              DB::table('account_client_receipts')
                  ->where('receipt_type', 1)
                  ->where('client_fund_ledger_type', 'Fee Transfer')
                  ->where('invoice_no', $oldInvoiceNo)
                  ->where('client_id', $clientId)
                  ->where('receipt_id', $depositEntry->receipt_id)
                  ->update([
                      'void_fee_transfer' => 1,
                      'updated_at' => now(),
                  ]);
              
              $invoicePaymentSyncLedger = app(InvoicePaymentSyncService::class);
              $syncOld = $invoicePaymentSyncLedger->persistPaymentState((int) $clientId, (string) $oldInvoiceNo);
              if ($syncOld !== null) {
                  Log::info('Old invoice status updated after re-allocation', [
                      'old_invoice_no' => $oldInvoiceNo,
                      'new_status' => $syncOld['invoice_status'],
                      'new_balance' => $syncOld['new_balance'],
                  ]);
              }
          }
          
          // Update the deposit entry with new invoice number
          DB::table('account_client_receipts')
              ->where('id', $id)
              ->update([
                  'invoice_no' => $invoiceNo,
                  'updated_at' => now(),
              ]);
          
          // Check if a Fee Transfer already exists for this deposit
          $existingFeeTransfer = DB::table('account_client_receipts')
              ->where('receipt_type', 1)
              ->where('client_fund_ledger_type', 'Fee Transfer')
              ->where('invoice_no', $invoiceNo)
              ->where('client_id', $clientId)
              ->where('receipt_id', $depositEntry->receipt_id)
              ->first();
          
          if (!$existingFeeTransfer) {
              // Create a Fee Transfer entry (withdrawal from client funds to pay the invoice)
              $depositAmount = floatval($depositEntry->deposit_amount);
              
              // Calculate current balance for this client/matter
              $ledger_entries = DB::table('account_client_receipts')
                  ->select('deposit_amount', 'withdraw_amount', 'void_fee_transfer')
                  ->where('client_id', $clientId)
                  ->where('client_matter_id', $depositEntry->client_matter_id)
                  ->where('receipt_type', 1)
                  ->orderBy('id', 'asc')
                  ->get();
              
              $running_balance = 0;
              foreach($ledger_entries as $entry) {
                  // Skip voided fee transfers
                  if(isset($entry->void_fee_transfer) && $entry->void_fee_transfer == 1) {
                      continue;
                  }
                  $running_balance += floatval($entry->deposit_amount) - floatval($entry->withdraw_amount);
              }
              
              // Check if there are sufficient funds
              if ($depositAmount > $running_balance) {
                  return response()->json([
                      'status' => false,
                      'message' => 'Insufficient funds in client account. Available: $' . number_format($running_balance, 2),
                  ], 400);
              }
              
              // Generate transaction number for Fee Transfer
              $trans_no = $this->createTransactionNumber('Fee Transfer');
              
              // Calculate new running balance after withdrawal
              $new_balance = $running_balance - $depositAmount;
              
              // Insert Fee Transfer entry
              DB::table('account_client_receipts')->insert([
                  'user_id' => Auth::user()->id,
                  'client_id' => $clientId,
                  'client_matter_id' => $depositEntry->client_matter_id,
                  'receipt_id' => $depositEntry->receipt_id,
                  'receipt_type' => 1, // Client fund ledger
                  'trans_date' => $depositEntry->trans_date,
                  'entry_date' => $depositEntry->entry_date,
                  'invoice_no' => !empty($invoiceNo) ? $invoiceNo : null,
                  'trans_no' => $trans_no,
                  'client_fund_ledger_type' => 'Fee Transfer',
                  'description' => 'Fee transfer to invoice ' . $invoiceNo,
                  'deposit_amount' => 0,
                  'withdraw_amount' => $depositAmount,
                  'balance_amount' => $new_balance,
                  'validate_receipt' => 0,
                  'void_invoice' => 0,
                  'invoice_status' => 0,
                  'save_type' => 'final',
                  'hubdoc_sent' => 0,
                  'created_at' => now(),
                  'updated_at' => now(),
              ]);
              
              Log::info('Fee Transfer created', [
                  'trans_no' => $trans_no,
                  'amount' => $depositAmount,
                  'invoice_no' => $invoiceNo
              ]);
          }
          
          $invoicePaymentSyncLedger = app(InvoicePaymentSyncService::class);
          $syncedNew = $invoicePaymentSyncLedger->persistPaymentState((int) $clientId, (string) $invoiceNo);
          if ($syncedNew !== null) {
              Log::info('Invoice status updated after ledger allocation', [
                  'invoice_no' => $invoiceNo,
                  'total_paid' => $syncedNew['total_paid'],
                  'new_balance' => $syncedNew['new_balance'],
                  'new_status' => $syncedNew['invoice_status'],
              ]);
          }
          
          // Log activity
          $userName = Auth::user()->first_name . ' ' . Auth::user()->last_name;
          $formattedAmount = '$' . number_format(floatval($depositEntry->deposit_amount), 2);
          $transDate = date('d/m/Y', strtotime($depositEntry->trans_date));
          
          if ($isReallocation) {
              $subject = "Client Fund Deposit Re-allocated - {$formattedAmount} (Ref: {$depositEntry->trans_no})";
              
              $description = "<div class='activity-detail'>";
              $description .= "<p><strong>{$userName}</strong> re-allocated a client fund deposit:</p>";
              $description .= "<ul>";
              $description .= "<li><strong>Deposit Reference:</strong> {$depositEntry->trans_no}</li>";
              $description .= "<li><strong>Amount:</strong> {$formattedAmount}</li>";
              $description .= "<li><strong>Transaction Date:</strong> {$transDate}</li>";
              $description .= "<li><strong>Old Invoice:</strong> {$oldInvoiceNo} (Fee Transfer voided)</li>";
              $description .= "<li><strong>New Invoice:</strong> {$invoiceNo}</li>";
              $description .= "<li><strong>New Fee Transfer Created:</strong> Yes</li>";
              $description .= "</ul>";
              $description .= "</div>";
          } else {
              $subject = "Client Fund Deposit Allocated - {$formattedAmount} (Ref: {$depositEntry->trans_no})";
              
              $description = "<div class='activity-detail'>";
              $description .= "<p><strong>{$userName}</strong> allocated a client fund deposit to an invoice:</p>";
              $description .= "<ul>";
              $description .= "<li><strong>Deposit Reference:</strong> {$depositEntry->trans_no}</li>";
              $description .= "<li><strong>Amount:</strong> {$formattedAmount}</li>";
              $description .= "<li><strong>Transaction Date:</strong> {$transDate}</li>";
              $description .= "<li><strong>Allocated to Invoice:</strong> {$invoiceNo}</li>";
              $description .= "<li><strong>Fee Transfer Created:</strong> Yes</li>";
              $description .= "</ul>";
              $description .= "</div>";
          }
          
          $objs = new \App\Models\ActivitiesLog;
          $objs->client_id = $clientId;
          $objs->created_by = Auth::user()->id;
          $objs->description = $description;
          $objs->subject = $subject;
          $objs->activity_type = 'financial';
          $objs->task_status = 0;
          $objs->pin = 0;
          $objs->save();
          
          return response()->json([
              'status' => true,
              'message' => 'Client fund deposit allocated and fee transfer created successfully',
          ], 200);
          
      } catch (\Exception $e) {
          Log::error('updateClientFundLedger error: ' . $e->getMessage(), [
              'trace' => $e->getTraceAsString()
          ]);
          
          return response()->json([
              'status' => false,
              'message' => 'Database error: ' . $e->getMessage(),
          ], 500);
      }
  }

  private function getNextReceiptId($receipt_type)
  {
      $is_record_exist = DB::table('account_client_receipts')->select('receipt_id')->where('receipt_type', $receipt_type)->orderBy('receipt_id', 'desc')->first();
      return !$is_record_exist ? 1 : $is_record_exist->receipt_id + 1;
  }

  //Save Journal reports
  public function savejournalreport(Request $request, $id = NULL)
  {
      try {
          $requestData         =     $request->all();
          
          // Validate required fields
          if (empty($requestData['client_id'])) {
              return response()->json([
                  'status' => false,
                  'message' => 'Client ID is required',
                  'requestData' => [],
                  'awsUrl' => ''
              ], 400);
          }

          $this->ensureCrmRecordAccess((int) $requestData['client_id']);
          
          if ($request->hasfile('document_upload'))
      {
          if(!is_array($request->file('document_upload'))){
           $files[] = $request->file('document_upload');
          }else{
           $files = $request->file('document_upload');
          }

          $client_info = \App\Models\Admin::select('client_id')->where('id', $requestData['client_id'])->first();
          if(!empty($client_info)){
           $client_unique_id = $client_info->client_id;
          } else {
           $client_unique_id = "";
          }

          $doctype = isset($request->doctype)? $request->doctype : '';

          foreach ($files as $file) {
           $size = $file->getSize();
           $fileName = $file->getClientOriginalName();
           $explodeFileName = explode('.', $fileName);
           $name = time() . $file->getClientOriginalName();
           $filePath = $client_unique_id.'/'.$doctype.'/'. $name;
           Storage::disk('s3')->put($filePath, file_get_contents($file));
           $exploadename = explode('.', $name);

           $obj = new \App\Models\Document;
           $obj->file_name = $explodeFileName[0];
           $obj->filetype = $exploadename[1];
           $obj->user_id = Auth::user()->id;
           $obj->myfile = $name;

           $obj->client_id = $requestData['client_id'];
           $obj->type = $request->type;
           $obj->file_size = $size;
           $obj->doc_type = $doctype;
           $doc_saved = $obj->save();

           $insertedDocId = $obj->id;
          } //end foreach

          if($doc_saved){
           if($request->type == 'client'){
               $subject = 'added 1 journal receipt document';
               $objs = new ActivitiesLog;
               $objs->client_id = $requestData['client_id'];
               $objs->created_by = Auth::user()->id;
               $objs->description = '';
               $objs->subject = $subject;
               $objs->activity_type = 'document';
               $objs->task_status = 0;
               $objs->pin = 0;
               $objs->save();
           }
          }
      } else {
          $insertedDocId = null;
          $doc_saved = "";
      }

      if(isset($requestData['trans_date'])){
          $is_record_exist = DB::table('account_client_receipts')->select('receipt_id')->where('receipt_type',4)->orderBy('receipt_id', 'desc')->first();
          if(!$is_record_exist){
           $receipt_id = 1;
          } else {
           $receipt_id = $is_record_exist->receipt_id +1;
          }

          $finalArr = array();
          for($i=0; $i<count($requestData['trans_date']); $i++){
           $withdrawAmount = isset($requestData['withdraw_amount'][$i]) ? $requestData['withdraw_amount'][$i] : 0;
           $finalArr[$i]['trans_date'] = $requestData['trans_date'][$i];
           $finalArr[$i]['entry_date'] = $requestData['entry_date'][$i];
           $finalArr[$i]['trans_no'] = $requestData['trans_no'][$i];
           $finalArr[$i]['invoice_no'] = $requestData['invoice_no'][$i];
           $finalArr[$i]['description'] = $requestData['description'][$i];
           $finalArr[$i]['withdrawal_amount'] = $withdrawAmount;

           $saved    = DB::table('account_client_receipts')->insert([
               'user_id' => $requestData['loggedin_staffid'] ?? $requestData['loggedin_userid'] ?? null,
               'client_id' =>  $requestData['client_id'],
               'agent_id' => !empty($requestData['agent_id']) ? $requestData['agent_id'] : null,
               'receipt_id'=>  $receipt_id,
               'receipt_type' => $requestData['receipt_type'],
               'trans_date' => $requestData['trans_date'][$i],
               'entry_date' => $requestData['entry_date'][$i],
               'trans_no' => $requestData['trans_no'][$i],
               'invoice_no' => isset($requestData['invoice_no'][$i]) && $requestData['invoice_no'][$i] !== '' ? $requestData['invoice_no'][$i] : null,
               'description' => $requestData['description'][$i],
               'withdraw_amount' => $withdrawAmount,
               'uploaded_doc_id'=> $insertedDocId,
               'validate_receipt' => 0,
               'void_invoice' => 0,
               'invoice_status' => 0,
               'save_type' => 'final',
               'hubdoc_sent' => 0
           ]);
          }
      }
      
      if($saved) {
          $response['status']     =     true;
          $response['requestData']     = $finalArr;
          //Get total withdrawl amount
          $db_total_withdrawal_amount = DB::table('account_client_receipts')->where('client_id',$requestData['client_id'])->where('receipt_type',4)->sum('withdraw_amount');
          $response['db_total_withdrawal_amount']     = $db_total_withdrawal_amount;

          if($doc_saved){
           //Get AWS Url link
           $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
           $awsUrl = $url.$client_unique_id.'/'.$doctype.'/'.$name; $response['awsUrl'] = $awsUrl;

           $response['message'] = 'Journal receipt with document added successfully';
          } else {
           $response['message'] = 'Journal receipt added successfully';
           $response['awsUrl'] =  "";
          }
      }else{
          $response['awsUrl'] =  "";
          $response['requestData']     = "";
          $response['status']     =     false;
          $response['message']    =    'Please try again';
      }
        return response()->json($response);
      } catch (\Exception $e) {
          Log::error('Error in savejournalreport: ' . $e->getMessage(), [
              'request_data' => $request->except(['document_upload']),
              'trace' => $e->getTraceAsString()
          ]);
          
          return response()->json([
              'status' => false,
              'message' => 'An error occurred while saving the journal report. Please try again.',
              'requestData' => [],
              'awsUrl' => ''
          ], 500);
      }
  }

  public function genInvoice(Request $request, $id, $client_id = null){ 
      // ============= BACKWARD COMPATIBILITY: Get client_id if not provided =============
      $queryClientId = $client_id;
      if (empty($queryClientId)) {
          // Try to get from account_all_invoice_receipts first (by receipt_id only)
          $tempRecord = AccountAllInvoiceReceipt::where('receipt_type', 3)
              ->where('receipt_id', $id)
              ->first();
          
          if ($tempRecord && !empty($tempRecord->client_id)) {
              $queryClientId = $tempRecord->client_id;
          }
      }
      
      // Validate that we have a client_id (either provided or found)
      if (empty($queryClientId)) {
          abort(404, 'Invoice not found or client_id could not be determined');
      }

      $this->ensureCrmRecordAccess((int) $queryClientId);
      // ============= END BACKWARD COMPATIBILITY =============
      
      $record_get = AccountAllInvoiceReceipt::where('receipt_type', 3)
          ->where('receipt_id', $id)
          ->where('client_id', $queryClientId)
          ->get();
      
      // Validate invoice exists
      if ($record_get->isEmpty()) {
          abort(404, 'Invoice not found');
      }

      // Get receipt_id entry from account_client_receipts to check for cached PDF
      $receipt_entry = DB::table('account_client_receipts')
          ->where('receipt_id', $id)
          ->where('client_id', $queryClientId)
          ->where('receipt_type', 3)
          ->first();

      if ($receipt_entry && strtolower(trim((string) ($receipt_entry->save_type ?? ''))) === 'draft') {
          abort(403, 'Draft invoices cannot be viewed as PDF until finalized.');
      }

      // ============= START CACHING LOGIC =============
      
      // Check if PDF already exists in AWS (skip cache when regenerate=1 so PDF is rebuilt with current client data)
      if (!$request->has('regenerate') && $receipt_entry && !empty($receipt_entry->pdf_document_id)) {
          $existingPdf = DB::table('documents')
           ->where('id', $receipt_entry->pdf_document_id)
           ->first();
          
          if ($existingPdf && !empty($existingPdf->myfile)) {
           // PDF exists in AWS, return it
           if ($request->has('download')) {
               // Force download: fetch from S3 and stream with proper headers
               try {
                   $pdfContent = null;
                   
                   // Try to get file from S3 using the file key if available
                   if (!empty($existingPdf->myfile_key)) {
                       $s3Path = $existingPdf->myfile_key;
                       
                       // If myfile_key is just filename, construct full path
                       if (strpos($s3Path, '/') === false) {
                           // Get client_id from receipt_entry or record_get
                           $clientIdForPath = null;
                           if ($receipt_entry && !empty($receipt_entry->client_id)) {
                               $clientIdForPath = $receipt_entry->client_id;
                           } elseif (!empty($record_get) && count($record_get) > 0 && !empty($record_get[0]->client_id)) {
                               $clientIdForPath = $record_get[0]->client_id;
                           }
                           
                           if ($clientIdForPath) {
                               $client_info = DB::table('admins')->select('client_id')->where('id', $clientIdForPath)->first();
                               $client_unique_id = $client_info->client_id ?? 'unknown';
                               // Use doc_type from document if available, otherwise default to 'invoices'
                               $docType = $existingPdf->doc_type ?? 'invoices';
                               $s3Path = $client_unique_id . '/' . $docType . '/' . $s3Path;
                           }
                       }
                       
                       // Try to get from S3
                       if (Storage::disk('s3')->exists($s3Path)) {
                           $pdfContent = Storage::disk('s3')->get($s3Path);
                       }
                   }
                   
                   // Fallback: download from URL if S3 get failed or key not available
                   if (empty($pdfContent)) {
                       $pdfContent = file_get_contents($existingPdf->myfile);
                   }
                   
                   return response($pdfContent, 200)
                       ->header('Content-Type', 'application/pdf')
                       ->header('Content-Disposition', 'attachment; filename="' . $existingPdf->file_name . '"');
               } catch (\Exception $e) {
                   // If S3 download fails, fallback to redirect (but won't force download)
                   Log::warning('Failed to download PDF from S3 for invoice ' . $id . ': ' . $e->getMessage());
                   return redirect()->away($existingPdf->myfile);
               }
           } else {
               // Stream in browser
               return redirect()->away($existingPdf->myfile);
           }
          }
      }
      
      // ============= PDF DATA PREPARATION =============

      $record_get_Professional_Fee_cnt = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Professional Fee')->count();
      $record_get_Department_Charges_cnt = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Department Charges')->count();
      $record_get_Surcharge_cnt = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Surcharge')->count();
      $record_get_Disbursements_cnt = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Disbursements')->count();
      $record_get_Other_Cost_cnt = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Other Cost')->count();
      $record_get_Discount_cnt = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Discount')->count();
      $record_get_Professional_Fee = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Professional Fee')->get();
      $record_get_Department_Charges = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Department Charges')->get();
      $record_get_Surcharge = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Surcharge')->get();
      $record_get_Disbursements = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Disbursements')->get();
      $record_get_Other_Cost = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Other Cost')->get();
      $record_get_Discount = AccountAllInvoiceReceipt::where('client_id', $queryClientId)->where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Discount')->get();
      //Calculate Gross Amount
      $total_Gross_Amount = AccountAllInvoiceReceipt::where('client_id', $queryClientId)
          ->where('receipt_type', 3)
          ->where('receipt_id', $id)
          ->sum(DB::raw("
           CASE
               WHEN payment_type = 'Discount' AND gst_included = 'Yes' THEN -(withdraw_amount - (withdraw_amount / 11))
               WHEN payment_type = 'Discount' AND gst_included = 'No' THEN -withdraw_amount
               WHEN gst_included = 'Yes' THEN withdraw_amount - (withdraw_amount / 11)
               ELSE withdraw_amount
           END
          "));

      //Total Invoice Amount
      $total_Invoice_Amount = AccountAllInvoiceReceipt::where('client_id', $queryClientId)
          ->where('receipt_type', 3)
          ->where('receipt_id', $id)
          ->sum(DB::raw("CASE
           WHEN payment_type = 'Discount' THEN -withdraw_amount
           ELSE withdraw_amount
          END"));

      //Calculate GST
      $total_GST_amount =  $total_Invoice_Amount - $total_Gross_Amount;

      // FIX: Handle NULL values for amounts (prevent decimal casting errors in Blade template)
      if ($total_Gross_Amount === null) {
          $total_Gross_Amount = 0.00;
      }
      if ($total_Invoice_Amount === null) {
          $total_Invoice_Amount = 0.00;
      }
      if ($total_GST_amount === null) {
          $total_GST_amount = 0.00;
      }

      // Total Pending Amount (computed from invoice lines and payments; matches partial fee transfer / office receipts)
      $invoicePaymentSyncPdf = app(InvoicePaymentSyncService::class);
      $voidForPending = ($receipt_entry && isset($receipt_entry->void_invoice) && (int) $receipt_entry->void_invoice === 1) ? 1 : null;
      $total_Pending_amount = $invoicePaymentSyncPdf->pendingAmountForReceiptPdf(
          (int) $queryClientId,
          (int) $id,
          (float) $total_Invoice_Amount,
          $voidForPending
      );

    Log::info('Total Pending Amount: ' . $total_Pending_amount);

      $clientname = DB::table('admins')->where('id',$record_get[0]->client_id)->first();
      
      // Validate client exists
      if (!$clientname) {
          abort(404, 'Client not found for this invoice');
      }
      
      // Get client's current address from client_addresses table
      $clientAddress = DB::table('client_addresses')
          ->where('client_id', $record_get[0]->client_id)
          ->where('is_current', 1)
          ->first();
      
      // If no current address, get the most recent one
      if (!$clientAddress) {
          $clientAddress = DB::table('client_addresses')
           ->where('client_id', $record_get[0]->client_id)
           ->orderByRaw(\App\Models\ClientAddress::ORDER_BY_DISPLAY_SQL)
           ->orderByDesc('id')
           ->first();
      }
      
      // Merge address data into clientname object
      if ($clientAddress) {
          $clientname->address = $clientAddress->address_line_1 ?? $clientAddress->address ?? '';
          if (!empty($clientAddress->address_line_2)) {
           $clientname->address .= (!empty($clientname->address) ? ', ' : '') . $clientAddress->address_line_2;
          }
          $clientname->city = $clientAddress->suburb ?? '';
          $clientname->state = $clientAddress->state ?? '';
          $clientname->zip = $clientAddress->zip ?? '';
          $clientname->country = $clientAddress->country ?? '';
      }

      //Get payment method
      if( $record_get->count() > 0 && $record_get[0]->invoice_no != '') {
          $invoice_payment_method = '';
          $office_receipt = DB::table('account_client_receipts')->select('payment_method')->where('receipt_type',2)->where('invoice_no',$record_get[0]->invoice_no)->first();
          if($office_receipt){
           $invoice_payment_method = $office_receipt->payment_method; if($invoice_payment_method != "" ) {
               $invoice_payment_method = $invoice_payment_method;
           } else {
               $invoice_payment_method = '';
           }
          } else {
           $invoice_payment_method = '';
          }
      } else {
          $invoice_payment_method = '';
      }

      //Get client matter
      if( $record_get->count() > 0 && !empty($record_get[0]->client_matter_id)) {
          $client_matter_no = '';
          $client_matter_name = '';
          $client_matter_display = '';
          
          $client_info = DB::table('admins')->select('client_id')->where('id',$record_get[0]->client_id)->first();
          if($client_info){
           $client_unique_id = $client_info->client_id; } else {
           $client_unique_id = '';
          }

          $matter_info = DB::table('client_matters')
           ->join('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
           ->select('client_matters.client_unique_matter_no', 'matters.title as matter_name', 'matters.nick_name')
           ->where('client_matters.id', $record_get[0]->client_matter_id)
           ->first();
          
          if($matter_info){
           $client_unique_matter_no = $matter_info->client_unique_matter_no;
           $client_matter_no = $client_unique_id.'-'.$client_unique_matter_no;
           
           // Use full title (matter_name)
           $client_matter_name = $matter_info->matter_name ?? '';
           
           // Create display string with both name and number
           if (!empty($client_matter_name)) {
               $client_matter_display = $client_matter_name . ' (' . $client_matter_no . ')';
           } else {
               $client_matter_display = $client_matter_no;
           }
          } else {
           $client_unique_matter_no = '';
           $client_matter_no = '';
           $client_matter_display = '';
          }
      } else {
          $client_unique_matter_no = '';
          $client_matter_no = '';
          $client_matter_display = '';
      }

      try {
          // Generate PDF if it doesn't exist or was deleted
          $pdf = PDF::setOptions([
           'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
           'logOutputFile' => storage_path('logs/log.htm'),
           'tempDir' => storage_path('logs/')
          ])->loadView('emails.geninvoice',compact(
           ['record_get',
           'record_get_Professional_Fee_cnt',
           'record_get_Department_Charges_cnt',
           'record_get_Surcharge_cnt',
           'record_get_Disbursements_cnt',
           'record_get_Other_Cost_cnt',
           'record_get_Discount_cnt',

           'record_get_Professional_Fee',
           'record_get_Department_Charges',
           'record_get_Surcharge',
           'record_get_Disbursements',
           'record_get_Other_Cost',
           'record_get_Discount',

           'total_Gross_Amount',
           'total_Invoice_Amount',
           'total_GST_amount',
           'total_Pending_amount',

           'clientname',
           'invoice_payment_method',
           'client_matter_no',
           'client_matter_display'
          ]));
          
          // Save PDF to AWS S3
          $pdfContent = $pdf->output();
          $fileName = 'Invoice-' . ($record_get[0]->invoice_no ?? $id) . '.pdf';
          $client_unique_id = $clientname->client_id ?? 'unknown';
          $docType = 'invoices'; // Category for S3 storage
          $s3FileName = time() . '_' . uniqid() . '_' . $fileName;
          $filePath = $client_unique_id . '/' . $docType . '/' . $s3FileName;
          
          // Upload to S3
          Storage::disk('s3')->put($filePath, $pdfContent);
          $s3Url = $this->s3PublicUrl($filePath);
          
          // Get authenticated user ID
          $userId = Auth::check() ? Auth::user()->id : 1;
          
          // Save document reference in database
          $document = new \App\Models\Document;
          $document->file_name = $fileName;
          $document->filetype = 'pdf';
          $document->user_id = $userId;
          $document->myfile = $s3Url;
          $document->myfile_key = $s3FileName;
          $document->client_id = $record_get[0]->client_id;
          $document->type = 'invoice'; // Document type identifier
          $document->doc_type = $docType;
          $document->file_size = strlen($pdfContent);
          $document->save();
          
          // Update account_client_receipts with PDF document ID
          DB::table('account_client_receipts')
           ->where('receipt_id', $id)
           ->where('receipt_type', 3)
           ->where('client_id', $queryClientId)
           ->update(['pdf_document_id' => $document->id]);
          
          // Return appropriate response
          if ($request->has('download')) {
           // Force download: return PDF directly with download headers
           return $pdf->download($fileName);
          } else {
           // Stream in browser (redirect to S3 URL for preview)
           return redirect()->away($s3Url);
          }
          
      } catch (\Exception $e) {
          Log::error('PDF Generation/Upload Error: ' . $e->getMessage(), [
           'invoice_id' => $id,
           'trace' => $e->getTraceAsString()
          ]);
          
          // Fall back to direct PDF generation
          $pdf = PDF::setOptions([
           'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
           'logOutputFile' => storage_path('logs/log.htm'),
           'tempDir' => storage_path('logs/')
          ])->loadView('emails.geninvoice',compact(
           ['record_get',
           'record_get_Professional_Fee_cnt',
           'record_get_Department_Charges_cnt',
           'record_get_Surcharge_cnt',
           'record_get_Disbursements_cnt',
           'record_get_Other_Cost_cnt',
           'record_get_Discount_cnt',

           'record_get_Professional_Fee',
           'record_get_Department_Charges',
           'record_get_Surcharge',
           'record_get_Disbursements',
           'record_get_Other_Cost',
           'record_get_Discount',

           'total_Gross_Amount',
           'total_Invoice_Amount',
           'total_GST_amount',
           'total_Pending_amount',

           'clientname',
           'invoice_payment_method',
           'client_matter_no',
           'client_matter_display'
          ]));
          
          // Return appropriate response based on download parameter
          $pdfFileName = 'Invoice-' . ($record_get[0]->invoice_no ?? $id) . '.pdf';
          if ($request->has('download')) {
              return $pdf->download($pdfFileName);
          } else {
              return $pdf->stream($pdfFileName);
          }
      }
      
      // ============= END CACHING LOGIC =============
  }

  public function uploadclientreceiptdocument(Request $request)
  {
      $id = $request->clientid;
      if (!empty($id)) {
          $this->ensureCrmRecordAccess((int) $id);
      }
      $receipt_id = $request->receipt_id; // Get the receipt ID
      $client_matter_id = $request->client_matter_id; // Get the matter ID
      
      $client_info = \App\Models\Admin::select('client_id')->where('id', $id)->first();
      if (!empty($client_info)) {
          $client_id = $client_info->client_id;
      } else {
          $client_id = "";
      }
      
      // CRITICAL: Validate receipt belongs to this client (security)
      if ($receipt_id) {
          $receiptExists = DB::table('account_client_receipts')
              ->where('id', $receipt_id)
              ->where('client_id', $id)
              ->exists();
          if (!$receiptExists) {
              return response()->json([
                  'status' => false,
                  'message' => 'Invalid receipt ID'
              ], 400);
          }
      }
      
      // CRITICAL: Validate matter belongs to this client (security)
      $matter_unique_id = "";
      if($client_matter_id) {
          $matter_info = DB::table('client_matters')
              ->select('client_unique_matter_no')
              ->where('id', $client_matter_id)
              ->where('client_id', $id)
              ->first();
          if(!$matter_info) {
              return response()->json([
                  'status' => false,
                  'message' => 'Invalid matter ID'
              ], 400);
          }
          $matter_unique_id = $matter_info->client_unique_matter_no;
      }
      
      $doctype = isset($request->doctype)? $request->doctype : '';
      $uploadedDocumentId = null; // Store the uploaded document ID
      if ($request->hasfile('document_upload')) {
          if(!is_array($request->file('document_upload'))){
           $files[] = $request->file('document_upload');
          }else{
           $files = $request->file('document_upload');
          }

          foreach ($files as $file) {
           $size = $file->getSize();
           $fileName = $file->getClientOriginalName();
           $explodeFileName = explode('.', $fileName);

           //$document_upload = $this->uploadrenameFile($file, config('constants.documents'));

           //$file = $request->file('document_upload');
           // Sanitize filename to prevent issues with special characters
           $sanitizedFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());
           $name = time() . '_' . $sanitizedFilename;
           //$explodeFileName1 = explode('.', $name);
           //$filePath = 'documents/' . $name;
           // New folder structure: Client/Matter/Accounts
           if($matter_unique_id) {
               $filePath = $client_id.'/'.$matter_unique_id.'/accounts/'. $name;
           } else {
               $filePath = $client_id.'/accounts/'. $name;
           }
           
           // CRITICAL: Start transaction for data integrity
           DB::beginTransaction();
           try {
               Storage::disk('s3')->put($filePath, file_get_contents($file));

               //$exploadename = explode('.', $document_upload);
               $exploadename = explode('.', $name);

               $obj = new \App\Models\Document;
               $obj->file_name = $explodeFileName[0];
               $obj->filetype = $exploadename[1];
               $obj->user_id = Auth::user()->id;
               //$obj->myfile = $document_upload;
               $obj->myfile_key = $name;  // Store filename for backward compatibility
               $obj->myfile = $name;  // Keep original behavior - stores filename not URL

               $obj->client_id = $id;
               $obj->type = $request->type;
               $obj->file_size = $size;
               $obj->doc_type = $doctype;
               $obj->client_matter_id = $client_matter_id;  // Store matter ID
               $saved = $obj->save();
               
               // Store the uploaded document ID
               $uploadedDocumentId = $obj->id;
               
               // Update the receipt with the uploaded document ID
               if ($receipt_id && $obj->id) {
                   DB::table('account_client_receipts')
                       ->where('id', $receipt_id)
                       ->update(['uploaded_doc_id' => $obj->id]);
               }
               
               DB::commit();
           } catch (\Exception $e) {
               DB::rollBack();
               // Clean up S3 file if database operations failed
               Storage::disk('s3')->delete($filePath);
               Log::error('Document upload failed', [
                   'error' => $e->getMessage(),
                   'client_id' => $id,
                   'receipt_id' => $receipt_id
               ]);
               return response()->json([
                   'status' => false,
                   'message' => 'Failed to upload document: ' . $e->getMessage()
               ], 500);
           }
          }

          if($saved){
           
           if($request->type == 'client'){
               $subject = 'added 1 client receipt document';
               $objs = new ActivitiesLog;
               $objs->client_id = $id;
               $objs->created_by = Auth::user()->id;
               $objs->description = '';
               $objs->subject = $subject;
               $objs->activity_type = 'document';
               $objs->task_status = 0;
               $objs->pin = 0;
               $objs->save();

           }
           $response['status']     =     true;
           $response['message']    =    "You've successfully uploaded your client receipt document";
           // FIX: Only fetch the document that was just uploaded, not all documents
           // This ensures the response always shows the correct document for this receipt
           $fetchd = $uploadedDocumentId 
               ? \App\Models\Document::where('id', $uploadedDocumentId)->get()
               : collect(); // Return empty collection if no document ID was stored
           ob_start();
           foreach($fetchd as $fetch){
               $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
               ?>
               <tr class="drow" id="id_<?php echo $fetch->id; ?>">
                   <td><div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo $fetch->file_name; ?>" class="doc-row">
                       <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo $fetch->file_name; ?><?php echo '.'.$fetch->filetype; ?></span>
                   </div></td>
                   <td><?php echo $admin->first_name; ?></td>

                   <td><?php echo date('Y-m-d', strtotime($fetch->created_at)); ?></td>
                   <td>
                       <div class="dropdown d-inline">
                           <button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
                           <div class="dropdown-menu">
                               <a class="dropdown-item renamedoc" href="javascript:;">Rename</a>
                               <?php
                               $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                               ?>
                               <a target="_blank" class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Preview</a>

                               <?php
                               $explodeimg = explode('.',$fetch->myfile);
                               if($explodeimg[1] == 'jpg'|| $explodeimg[1] == 'png'|| $explodeimg[1] == 'jpeg'){
                               ?>
                                   <a target="_blank" class="dropdown-item" href="<?php echo URL::to('/document/download/pdf'); ?>/<?php echo $fetch->id; ?>">PDF</a>
                               <?php } ?>

                               <a download class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Download</a>

                               <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item deletenote" data-href="deletedocs" href="javascript:;" >Delete</a>
                           </div>
                       </div>
                   </td>
               </tr>
               <?php
           }
           $data = ob_get_clean();
           ob_start();
           foreach($fetchd as $fetch){
               $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
               ?>
               <div class="grid_list">
                   <div class="grid_col">
                       <div class="grid_icon">
                           <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?>
                       </div>
                       <div class="grid_content">
                           <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                           <div class="dropdown d-inline dropdown_ellipsis_icon">
                               <a class="dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                               <div class="dropdown-menu">
                                   <?php
                                   $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                                   ?>
                                   <a class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Preview</a>
                                   <a download class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Download</a>

                                   <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item deletenote" data-href="deletedocs" href="javascript:;" >Delete</a>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
               <?php
           }
           $griddata = ob_get_clean();
           $response['data']    =$data;
           $response['griddata']    =$griddata;
          }else{
           $response['status']     =     false;
           $response['message']    =    'Please try again';
          }
       }else{
        $response['status']     =     false;
          $response['message']    =    'Please try again';
       }
        return response()->json($response);
  }

  public function uploadofficereceiptdocument(Request $request)
  {
      $id = $request->clientid;
      if (!empty($id)) {
          $this->ensureCrmRecordAccess((int) $id);
      }
      $receipt_id = $request->receipt_id; // Get the receipt ID
      $client_matter_id = $request->client_matter_id; // Get the matter ID
      
      $client_info = \App\Models\Admin::select('client_id')->where('id', $id)->first();
      if (!empty($client_info)) {
          $client_id = $client_info->client_id;
      } else {
          $client_id = "";
      }
      
      // CRITICAL: Validate receipt belongs to this client (security)
      if ($receipt_id) {
          $receiptExists = DB::table('account_client_receipts')
              ->where('id', $receipt_id)
              ->where('client_id', $id)
              ->where('receipt_type', 2) // Office receipt
              ->exists();
          if (!$receiptExists) {
              return response()->json([
                  'status' => false,
                  'message' => 'Invalid office receipt ID'
              ], 400);
          }
      }
      
      // CRITICAL: Validate matter belongs to this client (security)
      $matter_unique_id = "";
      if($client_matter_id) {
          $matter_info = DB::table('client_matters')
              ->select('client_unique_matter_no')
              ->where('id', $client_matter_id)
              ->where('client_id', $id)
              ->first();
          if(!$matter_info) {
              return response()->json([
                  'status' => false,
                  'message' => 'Invalid matter ID'
              ], 400);
          }
          $matter_unique_id = $matter_info->client_unique_matter_no;
      }
      
      $doctype = isset($request->doctype)? $request->doctype : '';
      $uploadedDocumentId = null; // Store the uploaded document ID
      if ($request->hasfile('document_upload')) {
          if(!is_array($request->file('document_upload'))){
           $files[] = $request->file('document_upload');
          }else{
           $files = $request->file('document_upload');
          }

          foreach ($files as $file) {
           $size = $file->getSize();
           $fileName = $file->getClientOriginalName();
           $explodeFileName = explode('.', $fileName);

           //$document_upload = $this->uploadrenameFile($file, config('constants.documents'));

           //$file = $request->file('document_upload');
           // Sanitize filename to prevent issues with special characters
           $sanitizedFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());
           $name = time() . '_' . $sanitizedFilename;
           //$explodeFileName1 = explode('.', $name);
           //$filePath = 'documents/' . $name;
           // New folder structure: Client/Matter/Accounts
           if($matter_unique_id) {
               $filePath = $client_id.'/'.$matter_unique_id.'/accounts/'. $name;
           } else {
               $filePath = $client_id.'/accounts/'. $name;
           }
           
           // CRITICAL: Start transaction for data integrity
           DB::beginTransaction();
           try {
               Storage::disk('s3')->put($filePath, file_get_contents($file));

               //$exploadename = explode('.', $document_upload);
               $exploadename = explode('.', $name);

               $obj = new \App\Models\Document;
               $obj->file_name = $explodeFileName[0];
               $obj->filetype = $exploadename[1];
               $obj->user_id = Auth::user()->id;
               //$obj->myfile = $document_upload;
               $obj->myfile_key = $name;  // Store filename for backward compatibility
               $obj->myfile = $name;  // Keep original behavior - stores filename not URL

               $obj->client_id = $id;
               $obj->type = $request->type;
               $obj->file_size = $size;
               $obj->doc_type = $doctype;
               $obj->client_matter_id = $client_matter_id;  // Store matter ID
               $saved = $obj->save();
               
               // Store the uploaded document ID
               $uploadedDocumentId = $obj->id;
               
               // Update the receipt with the uploaded document ID
               if ($receipt_id && $obj->id) {
                   DB::table('account_client_receipts')
                       ->where('id', $receipt_id)
                       ->update(['uploaded_doc_id' => $obj->id]);
               }
               
               DB::commit();
           } catch (\Exception $e) {
               DB::rollBack();
               // Clean up S3 file if database operations failed
               Storage::disk('s3')->delete($filePath);
               Log::error('Office receipt document upload failed', [
                   'error' => $e->getMessage(),
                   'client_id' => $id,
                   'receipt_id' => $receipt_id
               ]);
               return response()->json([
                   'status' => false,
                   'message' => 'Failed to upload document: ' . $e->getMessage()
               ], 500);
           }
          }

          if($saved){
           
           if($request->type == 'client'){
               $subject = 'added 1 office receipt document';
               $objs = new ActivitiesLog;
               $objs->client_id = $id;
               $objs->created_by = Auth::user()->id;
               $objs->description = '';
               $objs->subject = $subject;
               $objs->activity_type = 'document';
               $objs->task_status = 0;
               $objs->pin = 0;
               $objs->save();

           }
           $response['status']     =     true;
           $response['message']    =    "You've successfully uploaded your office receipt document";
           // FIX: Only fetch the document that was just uploaded, not all documents
           // This ensures the response always shows the correct document for this receipt
           $fetchd = $uploadedDocumentId 
               ? \App\Models\Document::where('id', $uploadedDocumentId)->get()
               : collect(); // Return empty collection if no document ID was stored
           ob_start();
           foreach($fetchd as $fetch){
               $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
               ?>
               <tr class="drow" id="id_<?php echo $fetch->id; ?>">
                   <td><div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo $fetch->file_name; ?>" class="doc-row">
                       <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo $fetch->file_name; ?><?php echo '.'.$fetch->filetype; ?></span>
                   </div></td>
                   <td><?php echo $admin->first_name; ?></td>

                   <td><?php echo date('Y-m-d', strtotime($fetch->created_at)); ?></td>
                   <td>
                       <div class="dropdown d-inline">
                           <button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
                           <div class="dropdown-menu">
                               <a class="dropdown-item renamedoc" href="javascript:;">Rename</a>
                               <?php
                               $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                               ?>
                               <a target="_blank" class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Preview</a>

                               <?php
                               $explodeimg = explode('.',$fetch->myfile);
                               if($explodeimg[1] == 'jpg'|| $explodeimg[1] == 'png'|| $explodeimg[1] == 'jpeg'){
                               ?>
                                   <a target="_blank" class="dropdown-item" href="<?php echo URL::to('/document/download/pdf'); ?>/<?php echo $fetch->id; ?>">PDF</a>
                               <?php } ?>

                               <a download class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Download</a>

                               <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item deletenote" data-href="deletedocs" href="javascript:;" >Delete</a>
                           </div>
                       </div>
                   </td>
               </tr>
               <?php
           }
           $data = ob_get_clean();
           ob_start();
           foreach($fetchd as $fetch){
               $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
               ?>
               <div class="grid_list">
                   <div class="grid_col">
                       <div class="grid_icon">
                           <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?>
                       </div>
                       <div class="grid_content">
                           <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                           <div class="dropdown d-inline dropdown_ellipsis_icon">
                               <a class="dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                               <div class="dropdown-menu">
                                   <?php
                                   $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                                   ?>
                                   <a class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Preview</a>
                                   <a download class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Download</a>

                                   <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item deletenote" data-href="deletedocs" href="javascript:;" >Delete</a>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
               <?php
           }
           $griddata = ob_get_clean();
           $response['data']    =$data;
           $response['griddata']    =$griddata;
          }else{
           $response['status']     =     false;
           $response['message']    =    'Please try again';
          }
       }else{
        $response['status']     =     false;
          $response['message']    =    'Please try again';
       }
        return response()->json($response);
  }

  public function uploadjournalreceiptdocument(Request $request)
  {
      $id = $request->clientid;
      if (!empty($id)) {
          $this->ensureCrmRecordAccess((int) $id);
      }
      $receipt_id = $request->receipt_id; // Get the receipt ID
      $client_matter_id = $request->client_matter_id; // Get the matter ID
      
      $client_info = \App\Models\Admin::select('client_id')->where('id', $id)->first();
      if (!empty($client_info)) {
          $client_id = $client_info->client_id;
      } else {
          $client_id = "";
      }
      
      // CRITICAL: Validate journal entry belongs to this client (security)
      if ($receipt_id) {
          $receiptExists = DB::table('account_journal_entries')
              ->where('id', $receipt_id)
              ->where('client_id', $id)
              ->exists();
          if (!$receiptExists) {
              return response()->json([
                  'status' => false,
                  'message' => 'Invalid journal entry ID'
              ], 400);
          }
      }
      
      // CRITICAL: Validate matter belongs to this client (security)
      $matter_unique_id = "";
      if($client_matter_id) {
          $matter_info = DB::table('client_matters')
              ->select('client_unique_matter_no')
              ->where('id', $client_matter_id)
              ->where('client_id', $id)
              ->first();
          if(!$matter_info) {
              return response()->json([
                  'status' => false,
                  'message' => 'Invalid matter ID'
              ], 400);
          }
          $matter_unique_id = $matter_info->client_unique_matter_no;
      }
      
      $doctype = isset($request->doctype)? $request->doctype : '';
      $uploadedDocumentId = null; // Store the uploaded document ID
      if ($request->hasfile('document_upload')) {
          if(!is_array($request->file('document_upload'))){
           $files[] = $request->file('document_upload');
          }else{
           $files = $request->file('document_upload');
          }

          foreach ($files as $file) {
           $size = $file->getSize();
           $fileName = $file->getClientOriginalName();
           $explodeFileName = explode('.', $fileName);

           //$document_upload = $this->uploadrenameFile($file, config('constants.documents'));

           //$file = $request->file('document_upload');
           // Sanitize filename to prevent issues with special characters
           $sanitizedFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());
           $name = time() . '_' . $sanitizedFilename;
           //$explodeFileName1 = explode('.', $name);
           //$filePath = 'documents/' . $name;
           // New folder structure: Client/Matter/Accounts
           if($matter_unique_id) {
               $filePath = $client_id.'/'.$matter_unique_id.'/accounts/'. $name;
           } else {
               $filePath = $client_id.'/accounts/'. $name;
           }
           
           // CRITICAL: Start transaction for data integrity
           DB::beginTransaction();
           try {
               Storage::disk('s3')->put($filePath, file_get_contents($file));

               //$exploadename = explode('.', $document_upload);
               $exploadename = explode('.', $name);

               $obj = new \App\Models\Document;
               $obj->file_name = $explodeFileName[0];
               $obj->filetype = $exploadename[1];
               $obj->user_id = Auth::user()->id;
               //$obj->myfile = $document_upload;
               $obj->myfile_key = $name;  // Store filename for backward compatibility
               $obj->myfile = $name;  // Keep original behavior - stores filename not URL

               $obj->client_id = $id;
               $obj->type = $request->type;
               $obj->file_size = $size;
               $obj->doc_type = $doctype;
               $obj->client_matter_id = $client_matter_id;  // Store matter ID
               $saved = $obj->save();
               
               // Store the uploaded document ID
               $uploadedDocumentId = $obj->id;
               
               // Update the journal entry with the uploaded document ID
               if ($receipt_id && $obj->id) {
                   DB::table('account_journal_entries')
                       ->where('id', $receipt_id)
                       ->update(['uploaded_doc_id' => $obj->id]);
               }
               
               DB::commit();
           } catch (\Exception $e) {
               DB::rollBack();
               // Clean up S3 file if database operations failed
               Storage::disk('s3')->delete($filePath);
               Log::error('Journal receipt document upload failed', [
                   'error' => $e->getMessage(),
                   'client_id' => $id,
                   'receipt_id' => $receipt_id
               ]);
               return response()->json([
                   'status' => false,
                   'message' => 'Failed to upload document: ' . $e->getMessage()
               ], 500);
           }
          }

          if($saved){
           
           if($request->type == 'client'){
               $subject = 'added 1 journal receipt document';
               $objs = new ActivitiesLog;
               $objs->client_id = $id;
               $objs->created_by = Auth::user()->id;
               $objs->description = '';
               $objs->subject = $subject;
               $objs->activity_type = 'document';
               $objs->task_status = 0;
               $objs->pin = 0;
               $objs->save();
           }
           $response['status']     =     true;
           $response['message']    =    "You've successfully uploaded your journal receipt document";
           // FIX: Only fetch the document that was just uploaded, not all documents
           // This ensures the response always shows the correct document for this receipt
           $fetchd = $uploadedDocumentId 
               ? \App\Models\Document::where('id', $uploadedDocumentId)->get()
               : collect(); // Return empty collection if no document ID was stored
           ob_start();
           foreach($fetchd as $fetch){
               $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
               ?>
               <tr class="drow" id="id_<?php echo $fetch->id; ?>">
                   <td><div data-id="<?php echo $fetch->id; ?>" data-name="<?php echo $fetch->file_name; ?>" class="doc-row">
                       <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?> <span><?php echo $fetch->file_name; ?><?php echo '.'.$fetch->filetype; ?></span>
                   </div></td>
                   <td><?php echo $admin->first_name; ?></td>

                   <td><?php echo date('Y-m-d', strtotime($fetch->created_at)); ?></td>
                   <td>
                       <div class="dropdown d-inline">
                           <button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
                           <div class="dropdown-menu">
                               <a class="dropdown-item renamedoc" href="javascript:;">Rename</a>
                               <?php
                               $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                               ?>
                               <a target="_blank" class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Preview</a>

                               <?php
                               $explodeimg = explode('.',$fetch->myfile);
                               if($explodeimg[1] == 'jpg'|| $explodeimg[1] == 'png'|| $explodeimg[1] == 'jpeg'){
                               ?>
                                   <a target="_blank" class="dropdown-item" href="<?php echo URL::to('/document/download/pdf'); ?>/<?php echo $fetch->id; ?>">PDF</a>
                               <?php } ?>

                               <a download class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Download</a>

                               <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item deletenote" data-href="deletedocs" href="javascript:;" >Delete</a>
                           </div>
                       </div>
                   </td>
               </tr>
               <?php
           }
           $data = ob_get_clean();
           ob_start();
           foreach($fetchd as $fetch){
               $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
               ?>
               <div class="grid_list">
                   <div class="grid_col">
                       <div class="grid_icon">
                           <?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-file-image'); ?>
                       </div>
                       <div class="grid_content">
                           <span id="grid_<?php echo $fetch->id; ?>" class="gridfilename"><?php echo $fetch->file_name; ?></span>
                           <div class="dropdown d-inline dropdown_ellipsis_icon">
                               <a class="dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-ellipsis-v'); ?></a>
                               <div class="dropdown-menu">
                                   <?php
                                   $url = 'https://'.env('AWS_BUCKET').'.s3.'. env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
                                   ?>
                                   <a class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Preview</a>
                                   <a download class="dropdown-item" href="<?php echo $url.$client_id.'/'.$doctype.'/'.$fetch->myfile; ?>">Download</a>

                                   <a data-id="<?php echo $fetch->id; ?>" class="dropdown-item deletenote" data-href="deletedocs" href="javascript:;" >Delete</a>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
               <?php
           }
           $griddata = ob_get_clean();
           $response['data']    =$data;
           $response['griddata']    =$griddata;
          }else{
           $response['status']     =     false;
           $response['message']    =    'Please try again';
          }
       }else{
        $response['status']     =     false;
          $response['message']    =    'Please try again';
       }
        return response()->json($response);
  }

  public function invoicelist(Request $request)
  {
      $this->ensureAccountsClientFromRequest($request);

      // Get latest record per receipt_id using subquery (PostgreSQL compatible)
      // This replaces groupBy which doesn't work with SELECT * in PostgreSQL
      $query = AccountClientReceipt::whereIn('id', function($subquery) use ($request) {
          $subquery->select(DB::raw('MAX(id)'))
                   ->from('account_client_receipts')
                   ->where('receipt_type', 3)
                   ->groupBy('receipt_id');
          
          // Apply same filters to subquery
          if ($request->has('client_id') && trim($request->input('client_id')) != '') {
              $subquery->where('client_id', '=', $request->input('client_id'));
          }
          if ($request->has('client_matter_id') && trim($request->input('client_matter_id')) != '') {
              $subquery->where('client_matter_id', '=', $request->input('client_matter_id'));
          }
      })->where('receipt_type', 3);
      
      // Filter: Client ID
      if ($request->has('client_id') && trim($request->input('client_id')) != '') {
          $query->where('client_id', '=', $request->input('client_id'));
      }

      // Filter: Client Matter ID
      if ($request->has('client_matter_id') && trim($request->input('client_matter_id')) != '') {
          $query->where('client_matter_id', '=', $request->input('client_matter_id'));
      }

      // Enhanced Date Filtering
      $this->applyDateFilters($query, $request);

      // Filter: Amount
      if ($request->has('amount') && trim($request->input('amount')) != '') {
          $amount = trim($request->input('amount'));
          $query->where(function($q) use ($amount) {
           $q->where('balance_amount', '=', $amount)
             ->orWhere('partial_paid_amount', '=', $amount);
          });
      }

      // Filter: Hubdoc Status
      if ($request->has('hubdoc_status') && $request->input('hubdoc_status') !== '') {
          $hubdocStatus = $request->input('hubdoc_status');
          if ($hubdocStatus == '1') {
           // Show only sent to Hubdoc
           $query->where('hubdoc_sent', '=', 1);
          } elseif ($hubdocStatus == '0') {
           // Show only NOT sent to Hubdoc
           $query->where(function($q) {
               $q->whereNull('hubdoc_sent')
                 ->orWhere('hubdoc_sent', '=', 0);
           });
          }
      }

      // Sorting
      $sortBy = $request->input('sort_by', 'id');
      $sortOrder = $request->input('sort_order', 'desc');
      
      // Map sort fields to database columns
      $sortMapping = [
          'client_id' => 'client_id',
          'client_matter' => 'client_matter_id',
          'name' => 'client_id', // Will be sorted by client_id which represents the name relation
          'reference' => 'trans_no',
          'trans_date' => 'trans_date',
          'amount' => 'balance_amount',
          'hubdoc_status' => 'hubdoc_sent',
          'voided_by' => 'voided_or_validated_by'
      ];
      
      $sortColumn = isset($sortMapping[$sortBy]) ? $sortMapping[$sortBy] : 'id';
      $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';
      
      $query->orderBy($sortColumn, $sortOrder);
      
     $totalData     = $query->count();

     // Handle items per page selection (defaults to 20 and only allow whitelisted values)
     $allowedPerPage = [10, 20, 50, 100, 200, 500];
     $perPageRequest = (int) $request->get('per_page', 20);
     $perPage = in_array($perPageRequest, $allowedPerPage, true) ? $perPageRequest : 20;

     $lists = $query->paginate($perPage);

      // Dropdown: Client list with receipts
      $clientIds = DB::table('account_client_receipts as acr')
          ->join('admins', 'admins.id', '=', 'acr.client_id')
          ->select('acr.client_id', 'admins.first_name', 'admins.last_name', 'admins.client_id as client_unique_id')
          ->distinct()
          ->orderBy('admins.first_name', 'asc')
          ->get();

      // Dropdown: Matter list with receipts
      $matterIds = DB::table('account_client_receipts as acr')
          ->join('client_matters', 'client_matters.id', '=', 'acr.client_matter_id')
          ->join('admins', 'admins.id', '=', 'acr.client_id')
          ->select('acr.client_matter_id', 'client_matters.client_unique_matter_no', 'admins.client_id as client_unique_id')
          ->distinct()
          ->orderBy('admins.client_id', 'asc')
          ->get();
     return view('crm.clients.invoicelist', compact(['lists', 'totalData', 'clientIds', 'matterIds', 'perPage']));
  }

  public function void_invoice(Request $request){
      Log::info('========== VOID_INVOICE CALLED ==========', ['request' => $request->all()]);
      
      $response = array();
      if( isset($request->clickedReceiptIds) && !empty($request->clickedReceiptIds) ){
          foreach ($request->clickedReceiptIds as $clickedVal) {
              $row = AccountClientReceipt::select('client_id')->where('receipt_type', 3)->where('receipt_id', $clickedVal)->first();
              if ($row && $row->client_id) {
                  $this->ensureCrmRecordAccess((int) $row->client_id);
              }
          }
          //Update all selected invoice bit to be 1
          $affectedRows = DB::table('account_client_receipts')
          ->where('receipt_type', 3)
          ->whereIn('receipt_id', $request->clickedReceiptIds)
          ->update(['void_invoice' => 1,'voided_or_validated_by' => Auth::user()->id,'invoice_status' => 3]); //invoice_status =3 voided
          
          $totalReversalsCreated = 0; // Track total reversals created
          
          if ($affectedRows > 0) {

           //update all invoices deposit amount to be zero
           foreach($request->clickedReceiptIds as $clickedKey=>$clickedVal){

               //Save in activity log
               $invoice_info = AccountClientReceipt::select('user_id','client_id','client_matter_id','invoice_no','trans_no','receipt_id')
                   ->where('receipt_type', 3)
                   ->where('receipt_id', $clickedVal)
                   ->first();
               
               // DEBUG: Add to log
               Log::info('VOID INVOICE - Got invoice_info', [
                   'clicked_receipt_id' => $clickedVal,
                   'invoice_info' => $invoice_info ? $invoice_info->toArray() : 'NULL'
               ]);
               
               $client_info = \App\Models\Admin::select('client_id')->where('id', $invoice_info->client_id)->first();
               $subject = 'voided invoice Sno -'.$clickedVal.' of client-'.$client_info->client_id;
               $objs = new ActivitiesLog;
               $objs->client_id = $invoice_info->client_id;
               $objs->created_by = Auth::user()->id;
               $objs->description = '';
               $objs->subject = $subject;
               $objs->activity_type = 'financial';
               $objs->task_status = 0;
               $objs->pin = 0;
               $objs->save();

               $record_info = DB::table('account_client_receipts')
               ->select('id','withdraw_amount','receipt_id','balance_amount','partial_paid_amount')
               ->where('receipt_type', 3)
               ->where('receipt_id', $clickedVal)
               ->where('void_invoice', 1)
               ->get();
               
               $invoiceAmount = 0; // Track the invoice amount
               
               if(!empty($record_info)){
                   foreach($record_info as $infoVal){
                       // Get the invoice amount (withdraw_amount is the invoice total)
                       // Use partial_paid_amount if available, otherwise use withdraw_amount
                       $paidAmount = floatval($infoVal->partial_paid_amount ?? 0);
                       $invoiceTotal = floatval($infoVal->withdraw_amount ?? 0);
                       
                       // Use whichever is greater (the actual invoice amount)
                       $invoiceAmount = max($paidAmount, $invoiceTotal);
                       
                       DB::table('account_client_receipts')
                       ->where('id',$infoVal->id)
                       ->update(['withdraw_amount_before_void' => $infoVal->balance_amount,'withdraw_amount'=>'0.00','balance_amount'=>'0.00','partial_paid_amount'=>'0.00']);
                   }
               }

               //update account_all_invoice_receipts entries also
               $record_info1 = AccountAllInvoiceReceipt::select('id','withdraw_amount','receipt_id')
               ->where('receipt_id', $clickedVal)
               ->get();
               if(!empty($record_info1)){
                   foreach($record_info1 as $infoVal1){
                      AccountAllInvoiceReceipt::where('receipt_id',$infoVal1->receipt_id)
                       ->update(['withdraw_amount_before_void' => $infoVal1->withdraw_amount,'withdraw_amount'=>'0.00','invoice_status'=>'3']); //void
                   }
               }

               // **NEW: REVERSE FEE TRANSFERS - Return money to client funds ledger**
               // Find all fee transfers linked to this invoice
               // Try multiple methods to find related fee transfers
               
               Log::info('Starting fee transfer search', [
                   'invoice_info' => [
                       'client_id' => $invoice_info->client_id ?? 'NULL',
                       'client_matter_id' => $invoice_info->client_matter_id ?? 'NULL',
                       'invoice_no' => $invoice_info->invoice_no ?? 'NULL',
                       'trans_no' => $invoice_info->trans_no ?? 'NULL',
                   ],
                   'calculated_amount' => $invoiceAmount
               ]);
               
               // Method 1: By invoice number (if stored)
               $feeTransfersQuery = DB::table('account_client_receipts')
                   ->where('receipt_type', 1)
                   ->where('client_fund_ledger_type', 'Fee Transfer')
                   ->where('client_id', $invoice_info->client_id);
               
               if(!empty($invoice_info->client_matter_id)){
                   $feeTransfersQuery->where('client_matter_id', $invoice_info->client_matter_id);
               }
               
               // Try with invoice_no first
               $feeTransfers = $feeTransfersQuery->where('invoice_no', $invoice_info->invoice_no)->get();
               
               // TEMPORARY DEBUG: Dump what we're searching for
               if(count($feeTransfers) == 0){
                   // Let's see what's in the database
                   $debugCheck = DB::table('account_client_receipts')
                       ->select('id','trans_no','invoice_no','client_id','client_matter_id')
                       ->where('receipt_type', 1)
                       ->where('client_fund_ledger_type', 'Fee Transfer')
                       ->where('client_id', $invoice_info->client_id)
                       ->get();
                   
                   Log::error('Fee transfer NOT found - Debug Info', [
                       'searching_for' => [
                           'invoice_no' => $invoice_info->invoice_no,
                           'client_id' => $invoice_info->client_id,
                           'client_matter_id' => $invoice_info->client_matter_id,
                       ],
                       'all_fee_transfers_for_client' => $debugCheck->toArray()
                   ]);
               }
               
               // Method 2: If no results, search by invoice amount and NOT already voided
               if(count($feeTransfers) == 0){
                   Log::info('No fee transfers found by invoice_no, trying by amount and date', [
                       'invoice_amount' => $invoiceAmount
                   ]);
                   
                   if($invoiceAmount > 0){
                       $feeTransfersQuery2 = DB::table('account_client_receipts')
                           ->where('receipt_type', 1)
                           ->where('client_fund_ledger_type', 'Fee Transfer')
                           ->where('client_id', $invoice_info->client_id)
                           ->where('withdraw_amount', $invoiceAmount)
                       ->where(function($q) {
                           $q->whereNull('void_fee_transfer')
                             ->orWhere('void_fee_transfer', 0);
                       })
                       ->where(function($q) use ($invoice_info) {
                           $q->where('invoice_no', $invoice_info->invoice_no)
                             ->orWhere('invoice_no', 'LIKE', '%'.$invoice_info->trans_no.'%')
                             ->orWhereNull('invoice_no')
                             ->orWhere('invoice_no', '');
                       });
                       
                       if(!empty($invoice_info->client_matter_id)){
                           $feeTransfersQuery2->where('client_matter_id', $invoice_info->client_matter_id);
                       }
                       
                       $feeTransfers = $feeTransfersQuery2->get();
                   }
               }

               // Debug: Log what we found
               Log::info('Void Invoice - Fee Transfer Search Results', [
                   'search_invoice_no' => $invoice_info->invoice_no,
                   'invoice_trans_no' => $invoice_info->trans_no,
                   'client_id' => $invoice_info->client_id,
                   'client_matter_id' => $invoice_info->client_matter_id,
                   'calculated_invoice_amount' => $invoiceAmount,
                   'fee_transfers_found' => count($feeTransfers),
                   'fee_transfer_details' => $feeTransfers->map(function($ft) {
                       return [
                           'id' => $ft->id,
                           'trans_no' => $ft->trans_no,
                           'invoice_no' => $ft->invoice_no ?? 'NULL',
                           'withdraw_amount' => $ft->withdraw_amount
                       ];
                   })->toArray()
               ]);

               if(!empty($feeTransfers) && count($feeTransfers) > 0){
                   foreach($feeTransfers as $feeTransfer){
                       // Only reverse if there was an actual withdrawal
                       $withdrawAmount = floatval($feeTransfer->withdraw_amount ?? 0);
                       if($withdrawAmount > 0){
                           // **MARK THE ORIGINAL FEE TRANSFER AS VOIDED**
                           DB::table('account_client_receipts')
                               ->where('id', $feeTransfer->id)
                               ->update([
                                   'void_fee_transfer' => 1,
                                   'voided_at' => now(),
                                   'voided_by' => Auth::user()->id
                               ]);
                           
                           $totalReversalsCreated++;

                           // Log the reversal activity
                           $reversal_subject = 'Voided Fee Transfer ' . $feeTransfer->trans_no . ' for voided invoice ' . $invoice_info->trans_no . ' - Returned $' . number_format($withdrawAmount, 2) . ' to client funds';
                           $reversal_activity = new ActivitiesLog;
                           $reversal_activity->client_id = $invoice_info->client_id;
                           $reversal_activity->created_by = Auth::user()->id;
                           $reversal_activity->description = 'Fee Transfer voided - Amount no longer withdrawn from client funds';
                           $reversal_activity->subject = $reversal_subject;
                           $reversal_activity->activity_type = 'financial';
                           $reversal_activity->task_status = 0;
                           $reversal_activity->pin = 0;
                           $reversal_activity->save();
                           
                           Log::info('Fee Transfer marked as voided', [
                               'fee_transfer_id' => $feeTransfer->id,
                               'trans_no' => $feeTransfer->trans_no,
                               'amount' => $withdrawAmount
                           ]);
                       }
                   }
                   
                   // **RECALCULATE ALL BALANCES FOR THIS CLIENT'S LEDGER**
                   // Get all non-voided entries ordered by ID
                   $allEntriesQuery = DB::table('account_client_receipts')
                       ->where('client_id', $invoice_info->client_id)
                       ->where('receipt_type', 1)
                       ->where(function($query) {
                           $query->whereNull('void_fee_transfer')
                                 ->orWhere('void_fee_transfer', 0);
                       })
                       ->orderBy('id', 'asc');
                   
                   if(!empty($invoice_info->client_matter_id)){
                       $allEntriesQuery->where('client_matter_id', $invoice_info->client_matter_id);
                   }
                   
                   $allEntries = $allEntriesQuery->get();
                   
                   // Recalculate running balance
                   $runningBalance = 0;
                   foreach($allEntries as $entry){
                       $runningBalance += floatval($entry->deposit_amount) - floatval($entry->withdraw_amount);
                       
                       DB::table('account_client_receipts')
                           ->where('id', $entry->id)
                           ->update(['balance_amount' => $runningBalance]);
                   }
                   
                   Log::info('Client Funds Ledger balances recalculated', [
                       'client_id' => $invoice_info->client_id,
                       'final_balance' => $runningBalance,
                       'entries_processed' => count($allEntries)
                   ]);
               }
           }

           //Get record For strike line through
           $record_data = DB::table('account_client_receipts')
           ->leftJoin('staff', 'staff.id', '=', 'account_client_receipts.voided_or_validated_by')
           ->select('account_client_receipts.id','account_client_receipts.voided_or_validated_by','staff.first_name','staff.last_name')
           ->where('account_client_receipts.receipt_type', 3)
           ->whereIn('account_client_receipts.receipt_id', $request->clickedReceiptIds)
           ->where('account_client_receipts.void_invoice', 1)
           ->get();
           $response['record_data'] =     $record_data;
           $response['status']     =     true;
           $response['reversals_created'] = $totalReversalsCreated;
           
           if($totalReversalsCreated > 0){
               $response['message'] = 'Invoice voided successfully. ' . $totalReversalsCreated . ' fee transfer(s) voided and balances recalculated.';
           } else {
               $response['message'] = 'Invoice voided successfully. (Note: No fee transfers found to reverse - invoice may not have been paid from client funds)';
           }
           
           // Add debug info
           $response['debug_info'] = [
               'total_reversals' => $totalReversalsCreated,
               'voided_receipts' => count($request->clickedReceiptIds)
           ];
          } else {
           $response['status']     =     true;
           $response['message']    =    'No record was updated.';
           $response['clickedIds'] =     array();
          }
      }
        return response()->json($response);
  }

  public function clientreceiptlist(Request $request)
  {
      $this->ensureAccountsClientFromRequest($request);

      $query = AccountClientReceipt::where('receipt_type', 1);

      // Filter: Client ID
      if ($request->has('client_id') && trim($request->input('client_id')) != '') {
          $query->where('client_id', '=', $request->input('client_id'));
      }

      // Filter: Client Matter ID
      if ($request->has('client_matter_id') && trim($request->input('client_matter_id')) != '') {
          $query->where('client_matter_id', '=', $request->input('client_matter_id'));
      }

      // Enhanced Date Filtering
      $this->applyDateFilters($query, $request);

      // Filter: Type
      if ($request->has('client_fund_ledger_type') && trim($request->input('client_fund_ledger_type')) != '') {
          $query->where('client_fund_ledger_type', 'LIKE', $request->input('client_fund_ledger_type'));
      }

      // Filter: Amount (search in both deposit_amount and withdraw_amount columns)
      if ($request->has('amount') && trim($request->input('amount')) != '') {
          $amount = trim($request->input('amount'));
          $query->where(function($q) use ($amount) {
           $q->where('deposit_amount', 'LIKE', '%' . $amount . '%')
             ->orWhere('withdraw_amount', 'LIKE', '%' . $amount . '%');
          });
      }

      // Filter: Receipt Validate
      if ($request->has('receipt_validate') && trim($request->input('receipt_validate')) != '') {
          $receiptValidate = trim($request->input('receipt_validate'));
          $query->where('validate_receipt', '=', $receiptValidate);
      }

      // Sorting
      $sortBy = $request->input('sort_by', 'id');
      $sortOrder = $request->input('sort_order', 'desc');
      
      // Map sort fields to database columns
      $sortMapping = [
          'client_id' => 'client_id',
          'client_matter' => 'client_matter_id',
          'name' => 'client_id', // Will be sorted by client_id which represents the name relation
          'trans_date' => 'trans_date',
          'type' => 'client_fund_ledger_type',
          'reference' => 'trans_no',
          'funds_in' => 'deposit_amount',
          'funds_out' => 'withdraw_amount',
          'validate_receipt' => 'validate_receipt',
          'validated_by' => 'voided_or_validated_by'
      ];
      
      $sortColumn = isset($sortMapping[$sortBy]) ? $sortMapping[$sortBy] : 'id';
      $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';
      
      $query->orderBy($sortColumn, $sortOrder);

      // Total count for pagination/meta
      $totalData = $query->count();

      // Get records per page from request, default to 20
      $perPage = $request->get('per_page', 20);
      $perPage = in_array($perPage, [10, 20, 50, 100, 200, 500]) ? $perPage : 20;

      // Fetch paginated list
      $lists = $query->paginate($perPage);

      // Dropdown: Client list with receipts
      $clientIds = DB::table('account_client_receipts as acr')
          ->join('admins', 'admins.id', '=', 'acr.client_id')
          ->select('acr.client_id', 'admins.first_name', 'admins.last_name', 'admins.client_id as client_unique_id')
          ->distinct()
          ->orderBy('admins.first_name', 'asc')
          ->get();

      // Dropdown: Matter list with receipts
      $matterIds = DB::table('account_client_receipts as acr')
          ->join('client_matters', 'client_matters.id', '=', 'acr.client_matter_id')
          ->join('admins', 'admins.id', '=', 'acr.client_id')
          ->select('acr.client_matter_id', 'client_matters.client_unique_matter_no', 'admins.client_id as client_unique_id')
          ->distinct()
          ->orderBy('admins.client_id', 'asc')
          ->get();

      return view('crm.clients.clientreceiptlist', compact(['lists', 'totalData', 'clientIds', 'matterIds', 'perPage']));
  }

  public function officereceiptlist(Request $request)
  {
      $this->ensureAccountsClientFromRequest($request);

      $query     = AccountClientReceipt::where('receipt_type',2);
      // Filter: Client ID
      if ($request->has('client_id') && trim($request->input('client_id')) != '') {
          $query->where('client_id', '=', $request->input('client_id'));
      }

      // Filter: Client Matter ID
      if ($request->has('client_matter_id') && trim($request->input('client_matter_id')) != '') {
          $query->where('client_matter_id', '=', $request->input('client_matter_id'));
      }

      // Enhanced Date Filtering
      $this->applyDateFilters($query, $request);

      // Filter: Amount
      if ($request->has('amount') && trim($request->input('amount')) != '') {
          $amount = trim($request->input('amount'));
          $query->where('deposit_amount', 'LIKE', '%' . $amount . '%');
      }

      // Filter: Validate Receipt
      if ($request->has('validate_receipt') && trim($request->input('validate_receipt')) != '') {
          $validateReceipt = trim($request->input('validate_receipt'));
          $query->where('validate_receipt', '=', $validateReceipt);
      }

      // Sorting
      $sortBy = $request->input('sort_by', 'id');
      $sortOrder = $request->input('sort_order', 'desc');
      
      // Map sort fields to database columns
      $sortMapping = [
          'client_id' => 'client_id',
          'client_matter' => 'client_matter_id',
          'name' => 'client_id', // Will be sorted by client_id which represents the name relation
          'trans_date' => 'trans_date',
          'reference' => 'trans_no',
          'invoice_no' => 'invoice_no',
          'payment_method' => 'payment_method',
          'amount' => 'deposit_amount',
          'validate_receipt' => 'validate_receipt',
          'validated_by' => 'voided_or_validated_by'
      ];
      
      $sortColumn = isset($sortMapping[$sortBy]) ? $sortMapping[$sortBy] : 'id';
      $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';
      
      $query->orderBy($sortColumn, $sortOrder);
      
     $totalData     = $query->count();

     // Handle items per page selection (defaults to 20 and only allow whitelisted values)
     $allowedPerPage = [10, 20, 50, 100, 200, 500];
     $perPageRequest = (int) $request->get('per_page', 20);
     $perPage = in_array($perPageRequest, $allowedPerPage, true) ? $perPageRequest : 20;

     $lists = $query->paginate($perPage);

      // Dropdown: Client list with receipts
      $clientIds = DB::table('account_client_receipts as acr')
          ->join('admins', 'admins.id', '=', 'acr.client_id')
          ->select('acr.client_id', 'admins.first_name', 'admins.last_name', 'admins.client_id as client_unique_id')
          ->distinct()
          ->orderBy('admins.first_name', 'asc')
          ->get();

      // Dropdown: Matter list with receipts
      $matterIds = DB::table('account_client_receipts as acr')
          ->join('client_matters', 'client_matters.id', '=', 'acr.client_matter_id')
          ->join('admins', 'admins.id', '=', 'acr.client_id')
          ->select('acr.client_matter_id', 'client_matters.client_unique_matter_no', 'admins.client_id as client_unique_id')
          ->distinct()
          ->orderBy('admins.client_id', 'asc')
          ->get();

     return view('crm.clients.officereceiptlist', compact(['lists', 'totalData', 'clientIds', 'matterIds', 'perPage']));
  }

  public function journalreceiptlist(Request $request)
  {
      // PostgreSQL requires all non-aggregate columns in GROUP BY
      $query     = AccountClientReceipt::select('id','receipt_id','client_id','user_id','trans_date','entry_date','trans_no', 'invoice_no','payment_method','validate_receipt','voided_or_validated_by', DB::raw('sum(withdraw_amount) as total_withdrawal_amount'))
          ->where('receipt_type',4)
          ->groupBy('id','receipt_id','client_id','user_id','trans_date','entry_date','trans_no', 'invoice_no','payment_method','validate_receipt','voided_or_validated_by');
      
      // Enhanced Date Filtering
      $this->applyDateFilters($query, $request);
      
      // Sorting
      $sortBy = $request->input('sort_by', 'id');
      $sortOrder = $request->input('sort_order', 'desc');
      
      // Map sort fields to database columns
      $sortMapping = [
          'receipt_id' => 'receipt_id',
          'client_id' => 'client_id',
          'name' => 'client_id', // Will be sorted by client_id which represents the name relation
          'trans_date' => 'trans_date',
          'entry_date' => 'entry_date',
          'trans_no' => 'trans_no',
          'invoice_no' => 'invoice_no',
          'amount' => 'total_withdrawal_amount',
          'validate_receipt' => 'validate_receipt',
          'validated_by' => 'voided_or_validated_by'
      ];
      
      $sortColumn = isset($sortMapping[$sortBy]) ? $sortMapping[$sortBy] : 'id';
      $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? $sortOrder : 'desc';
      
      $query->orderBy($sortColumn, $sortOrder);
      
      $totalData     = $query->count();
      $lists = $query->paginate(20);
      return view('crm.clients.journalreceiptlist', compact(['lists', 'totalData']));
  }

  /**
   * Analytics Dashboard
   * 
   * Display comprehensive financial statistics and analytics
   */
  public function analyticsDashboard(Request $request)
  {
      // Restrict to admin and super admin only (roles 1, 12)
      if (!in_array(Auth::user()->role ?? 0, [1, 12])) {
          return redirect()->back()->with('error', 'Only admin and super admin can view the analytics dashboard.');
      }

      $statsService = new FinancialStatsService();
      
      // Get quick_select parameter
      $quickSelect = $request->input('quick_select', '');
      
      // Get date range from request or default to current month
      $startDate = $request->has('start_date') 
          ? Carbon::parse($request->input('start_date')) 
          : Carbon::now()->startOfMonth();
      
      $endDate = $request->has('end_date') 
          ? Carbon::parse($request->input('end_date')) 
          : Carbon::now()->endOfMonth();
      
      // Get receipt_type filter (null = all, 1-4 = specific type)
      $receiptType = $request->has('receipt_type') && $request->input('receipt_type') !== ''
          ? (int)$request->input('receipt_type')
          : null;
      
      // Get all statistics
      $dashboardStats = $statsService->getDashboardStats([
          'start_date' => $startDate,
          'end_date' => $endDate,
          'receipt_type' => $receiptType,
      ]);
      
      // Get payment method breakdown
      $paymentMethods = $statsService->getPaymentMethodBreakdown($startDate, $endDate);
      
      return view('crm.clients.analytics-dashboard', compact([
          'dashboardStats',
          'paymentMethods',
          'startDate',
          'endDate',
          'receiptType',
          'quickSelect',
      ]));
  }

  public function validate_receipt(Request $request){
      try {
          $response = array();
          
          // Validate input
          if( !isset($request->clickedReceiptIds) || empty($request->clickedReceiptIds) ){
              $response['status'] = false;
              $response['message'] = 'No receipts selected.';
              return response()->json($response, 400);
          }
          
          if( !isset($request->receipt_type) ){
              $response['status'] = false;
              $response['message'] = 'Receipt type is required.';
              return response()->json($response, 400);
          }

          foreach ($request->clickedReceiptIds as $rid) {
              $row = AccountClientReceipt::select('client_id')->where('id', $rid)->where('receipt_type', $request->receipt_type)->first();
              if ($row && $row->client_id) {
                  $this->ensureCrmRecordAccess((int) $row->client_id);
              }
          }
          
          //Update all selected receipt bit to be 1
          $affectedRows = DB::table('account_client_receipts')
          ->where('receipt_type', $request->receipt_type)
          ->whereIn('id', $request->clickedReceiptIds)
          ->update(['validate_receipt' => 1,'voided_or_validated_by' => Auth::user()->id]);
          
          if ($affectedRows > 0) {
           foreach($request->clickedReceiptIds as $ReceiptVal){
               $receipt_info = AccountClientReceipt::select('user_id','client_id','trans_date')->where('id', $ReceiptVal)->first();
               
               if(!$receipt_info){
                   continue; // Skip if receipt not found
               }
               
               $client_info = \App\Models\Admin::select('client_id')->where('id', $receipt_info->client_id)->first();
               
               if(!$client_info){
                   continue; // Skip if client not found
               }

               if($request->receipt_type == 1){
                   $subject = 'validated client receipt no -'.$ReceiptVal.' of client-'.$client_info->client_id;
               } else if($request->receipt_type == 2){
                   $subject = 'validated office receipt no -'.$ReceiptVal.' of client-'.$client_info->client_id;
               } else if($request->receipt_type == 4){
                   $subject = 'validated journal receipt no -'.$ReceiptVal.' of client-'.$client_info->client_id;
               } else {
                   $subject = 'validated receipt no -'.$ReceiptVal;
               }
               
               $objs = new ActivitiesLog;
               $objs->client_id = $receipt_info->client_id;
               $objs->created_by = Auth::user()->id;
               $objs->description = '';
               $objs->subject = $subject;
               $objs->activity_type = 'financial';
               $objs->task_status = 0;
               $objs->pin = 0;
               $objs->save();
           }

           //Get record validate_receipt =1
           $record_data = DB::table('account_client_receipts')
           ->leftJoin('staff', 'staff.id', '=', 'account_client_receipts.voided_or_validated_by')
           ->select('account_client_receipts.id','account_client_receipts.voided_or_validated_by','account_client_receipts.trans_date','staff.first_name','staff.last_name')
           ->where('account_client_receipts.receipt_type', $request->receipt_type)
           ->whereIn('account_client_receipts.id', $request->clickedReceiptIds)
           ->where('account_client_receipts.validate_receipt', 1)
           ->get();
           
           $response['record_data'] = $record_data;
           $response['status'] = true;
           $response['message'] = 'Receipt validated successfully.';
          } else {
           $response['status'] = false;
           $response['message'] = 'No records were updated. Receipts may already be validated.';
           $response['clickedIds'] = array();
          }
          
          return response()->json($response);
          
      } catch (\Exception $e) {
          Log::error('Error validating receipt: ' . $e->getMessage(), [
              'receipt_ids' => $request->clickedReceiptIds ?? null,
              'receipt_type' => $request->receipt_type ?? null,
              'trace' => $e->getTraceAsString()
          ]);
          
          return response()->json([
              'status' => false,
              'message' => 'An error occurred while validating receipt: ' . $e->getMessage()
          ], 500);
      }
  }

  //Delete Receipt by Super admin
  public function delete_receipt(Request $request)
  {
      $response = array();
      if (isset($request->receiptId) && !empty($request->receiptId)) {
          // Ensure the user is a Super Admin (role = 1)
          // Optionally check for specific authorized email from config
          $authorizedEmail = config('app.super_admin_email');
          if (Auth::user()->role != '1' || (config('app.require_super_admin_email') && Auth::user()->email != $authorizedEmail)) {
           $response['status'] = false;
           $response['message'] = 'Unauthorized access.';
           return response()->json($response);
          }

          // Fetch the receipt to be deleted
          $receipt = AccountClientReceipt::where('id', $request->receiptId)
           ->where('receipt_type', $request->receipt_type)
           ->first();

          if (!$receipt) {
           $response['status'] = false;
           $response['message'] = 'Receipt not found.';
        return response()->json($response);
           return;
          }

          $this->ensureCrmRecordAccess((int) $receipt->client_id);

          // Check if the client_fund_ledger_type is 'Fee Transfer'
          if ($receipt->client_fund_ledger_type == 'Fee Transfer') {
           $response['status'] = false;
           $response['message'] = 'This entry is already associated with an Invoice, so it cannot be deleted. Please try another.';
        return response()->json($response);
           return;
          }

          // Store receipt details for balance adjustment and logging
          $client_id = $receipt->client_id;
          $deposit_amount = $receipt->deposit_amount ?? 0;
          $withdraw_amount = $receipt->withdraw_amount ?? 0;
          $receipt_id = $receipt->id;

          // Delete the receipt
          $affectedRows = AccountClientReceipt::where('id', $request->receiptId)
           ->where('receipt_type', $request->receipt_type)
           ->delete();

          if ($affectedRows > 0) {
           // Adjust balance (assuming a balance table or logic exists)
           // Example: Update client balance by reversing the transaction
           $client_info = \App\Models\Admin::select('id')->where('id', $client_id)->first();
           if ($client_info) {
               // This is a placeholder for balance adjustment logic
               // You may need to adjust this based on your actual balance management system
               // For example, if you have a ClientBalance model:
               // ClientBalance::where('client_id', $client_id)
               //     ->decrement('balance', $deposit_amount - $withdraw_amount);
           }

           // Log the activity
           $client_info = \App\Models\Admin::select('client_id')->where('id', $client_id)->first();
           $subject = 'Deleted client receipt no -' . $receipt_id . ' of client-' . ($client_info->client_id ?? 'N/A');
           $objs = new ActivitiesLog;
           $objs->client_id = $client_id;
           $objs->created_by = Auth::user()->id;
           $objs->description = '';
           $objs->subject = $subject;
           $objs->activity_type = 'financial';
           $objs->task_status = 0;
           $objs->pin = 0;
           $objs->save();

           $response['status'] = true;
           $response['message'] = 'Receipt deleted successfully.';
          } else {
           $response['status'] = false;
           $response['message'] = 'Failed to delete receipt.';
          }
      } else {
          $response['status'] = false;
          $response['message'] = 'No receipt selected.';
      }
        return response()->json($response);
  }

  public function printPreview(Request $request, $id){
      $record_get = DB::table('account_client_receipts')->where('receipt_type',1)->where('id',$id)->get();
      if ($record_get->isNotEmpty()) {
          $this->ensureCrmRecordAccess((int) $record_get[0]->client_id);
      }
      if($record_get){
          $clientname = DB::table('admins')->select('first_name','last_name','address','state','city','zip','country')->where('id',$record_get[0]->client_id)->first();
          $agentname = $record_get[0]->agent_id
              ? DB::table('agent_details')->where('id', $record_get[0]->agent_id)->first()
              : null;
          $admin = DB::table('staff')->select('company_name','business_address as address','state','city','zip','email','business_phone as phone')->where('id',$record_get[0]->user_id)->first();
      }
      $pdf = PDF::setOptions([
          'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
          'logOutputFile' => storage_path('logs/log.htm'),
          'tempDir' => storage_path('logs/')
      ])->loadView('emails.printpreview',compact(['record_get','clientname','agentname','admin']));
      return $pdf->stream('ClientReceipt.pdf');
  }

    public function genClientFundReceipt(Request $request, $id){
    $record_get = DB::table('account_client_receipts')->where('receipt_type',1)->where('id',$id)->first();
    // Validate receipt exists
    if (!$record_get) {
        abort(404, 'Receipt not found');
    }

    $this->ensureCrmRecordAccess((int) $record_get->client_id);
    
    $clientname = DB::table('admins')->where('id',$record_get->client_id)->first();
    
    // Validate client exists
    if (!$clientname) {
        abort(404, 'Client not found');
    }
    
    // Get client's current address from client_addresses table
    $clientAddress = DB::table('client_addresses')
        ->where('client_id', $record_get->client_id)
        ->where('is_current', 1)
        ->first();
    
    // If no current address, get the most recent one
    if (!$clientAddress) {
        $clientAddress = DB::table('client_addresses')
         ->where('client_id', $record_get->client_id)
         ->orderByRaw(\App\Models\ClientAddress::ORDER_BY_DISPLAY_SQL)
         ->orderByDesc('id')
         ->first();
    }
    
    // Merge address data into clientname object
    if ($clientAddress) {
        $clientname->address = $clientAddress->address_line_1 ?? $clientAddress->address ?? '';
        if (!empty($clientAddress->address_line_2)) {
         $clientname->address .= (!empty($clientname->address) ? ', ' : '') . $clientAddress->address_line_2;
        }
        $clientname->city = $clientAddress->suburb ?? '';
        $clientname->state = $clientAddress->state ?? '';
        $clientname->zip = $clientAddress->zip ?? '';
        $clientname->country = $clientAddress->country ?? '';
    }

    //Get client matter (use receipt's client_matter_id so PDF shows the correct matter)
    if( !empty($record_get) && $record_get->client_id != '') {
        $client_matter_no = '';
        $client_matter_name = '';
        $client_matter_display = '';
        
        $client_info = DB::table('admins')->select('client_id')->where('id',$record_get->client_id)->first();
        if($client_info){
         $client_unique_id = $client_info->client_id; } else {
         $client_unique_id = '';
        }

        $matterQuery = DB::table('client_matters')
         ->join('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
         ->select('client_matters.client_unique_matter_no', 'matters.title as matter_name', 'matters.nick_name')
         ->where('client_matters.client_id', $record_get->client_id);
        // Use receipt's matter when set; otherwise fallback to first matter (legacy receipts)
        if (!empty($record_get->client_matter_id)) {
            $matterQuery->where('client_matters.id', $record_get->client_matter_id);
        }
        $matter_info = $matterQuery->first();
        
        if($matter_info){
         $client_unique_matter_no = $matter_info->client_unique_matter_no;
         $client_matter_no = $client_unique_id.'-'.$client_unique_matter_no;
         
         // Use full title (matter_name)
         $client_matter_name = $matter_info->matter_name ?? '';
         
         // Create display string with both name and number
         if (!empty($client_matter_name)) {
             $client_matter_display = $client_matter_name . ' (' . $client_matter_no . ')';
         } else {
             $client_matter_display = $client_matter_no;
         }
        } else {
         $client_unique_matter_no = '';
         $client_matter_no = '';
         $client_matter_display = '';
        }
    } else {
        $client_matter_no = '';
        $client_matter_display = '';
    }

    // Check if PDF already exists in AWS (skip cache when regenerate=1 so PDF is regenerated with correct matter)
    if (!$request->has('regenerate') && !empty($record_get->pdf_document_id)) {
        $existingPdf = DB::table('documents')
         ->where('id', $record_get->pdf_document_id)
         ->first();
        
        if ($existingPdf && !empty($existingPdf->myfile)) {
         // PDF exists in AWS, return it
         if ($request->has('download')) {
             // Force download with proper headers
             $headers = [
                 'Content-Type' => 'application/pdf',
                 'Content-Disposition' => 'attachment; filename="' . $existingPdf->file_name . '"',
             ];
             return redirect()->away($existingPdf->myfile)->withHeaders($headers);
         } else {
             // Stream in browser
             return redirect()->away($existingPdf->myfile);
         }
        }
    }
    
    try {
        // Generate PDF if it doesn't exist or was deleted
        $pdf = PDF::setOptions([
         'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
         'logOutputFile' => storage_path('logs/log.htm'),
         'tempDir' => storage_path('logs/')
        ])->loadView('emails.genclientfundreceipt',compact(['record_get','clientname','client_matter_no','client_matter_display']));
        
        // Save PDF to AWS S3
        $pdfContent = $pdf->output();
        $fileName = 'Receipt-' . ($record_get->trans_no ?? $id) . '.pdf';
        $client_unique_id = $clientname->client_id ?? 'unknown';
        $docType = 'receipts';
        $s3FileName = time() . '_' . uniqid() . '_' . $fileName;
        $filePath = $client_unique_id . '/' . $docType . '/' . $s3FileName;
        
        // Upload to S3
        Storage::disk('s3')->put($filePath, $pdfContent);
        $s3Url = $this->s3PublicUrl($filePath);
        
        // Get authenticated user ID
        $userId = Auth::check() ? Auth::user()->id : 1;
        
        // Save document reference in database
        $document = new \App\Models\Document;
        $document->file_name = $fileName;
        $document->filetype = 'pdf';
        $document->user_id = $userId;
        $document->myfile = $s3Url;
        $document->myfile_key = $s3FileName;
        $document->client_id = $record_get->client_id;
        $document->type = 'client_fund_receipt';
        $document->doc_type = $docType;
        $document->file_size = strlen($pdfContent);
        $document->save();
        
        // Update account_client_receipts with PDF document ID
        DB::table('account_client_receipts')
         ->where('id', $id)
         ->update(['pdf_document_id' => $document->id]);
        
        // Return appropriate response
        if ($request->has('download')) {
         // Force download
         $headers = [
             'Content-Type' => 'application/pdf',
             'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
         ];
         return redirect()->away($s3Url)->withHeaders($headers);
        } else {
         // Stream in browser
         return redirect()->away($s3Url);
        }
        
    } catch (\Exception $e) {
        Log::error('PDF Generation/Upload Error: ' . $e->getMessage(), [
         'receipt_id' => $id,
         'trace' => $e->getTraceAsString()
        ]);
        
        // Fall back to direct PDF generation
        return $pdf->stream('Receipt-' . ($record_get->trans_no ?? $id) . '.pdf');
    }
}

    /**
     * Fix wrong matter on a client fund receipt (receipt_type=1): set client_matter_id, then regenerate PDF.
     * Requires auth:admin. Does not alter genClientFundReceipt logic — redirects with ?regenerate=1.
     *
     * Matter (one required): matter=PSA_1 or matter_no=PSA_1 (client_unique_matter_no for this receipt's client),
     * or client_matter_id as numeric id, or client_matter_id=PSA_1 (same short code).
     *
     * Receipt: /clients/fix-client-fund-receipt-matter/{id}?matter=PSA_1
     * Or: ?trans_no=CFL-5033&client_id={admins.id}&matter=PSA_1
     * Optional: &download=1
     *
     * @param  int|null  $id  Receipt row id when using path form
     */
    public function fixClientFundReceiptMatterAndRegenerate(Request $request, $id = null)
    {
        $receiptId = null;
        if ($id !== null && $id !== '') {
            if (!ctype_digit((string) $id)) {
                abort(422, 'Receipt id must be numeric.');
            }
            $receiptId = (int) $id;
        } elseif ($request->filled('receipt_id')) {
            $rid = $request->query('receipt_id');
            if (!ctype_digit((string) $rid)) {
                abort(422, 'receipt_id must be a positive integer.');
            }
            $receiptId = (int) $rid;
        } elseif ($request->filled('trans_no') && $request->filled('client_id')) {
            $transNo = trim((string) $request->query('trans_no'));
            $clientId = $request->query('client_id');
            if ($transNo === '' || !ctype_digit((string) $clientId)) {
                abort(422, 'trans_no and client_id (numeric admins.id) are required when not using receipt id.');
            }
            $clientId = (int) $clientId;
            $matches = DB::table('account_client_receipts')
                ->where('receipt_type', 1)
                ->where('client_id', $clientId)
                ->where('trans_no', $transNo)
                ->pluck('id');
            if ($matches->isEmpty()) {
                abort(404, 'No client fund receipt found for that trans_no and client_id.');
            }
            if ($matches->count() > 1) {
                abort(422, 'Multiple receipts match trans_no and client_id; use receipt id in URL with matter= or client_matter_id=.');
            }
            $receiptId = (int) $matches->first();
        } else {
            abort(422, 'Provide receipt id in path, or receipt_id=, or trans_no= and client_id=.');
        }

        $receipt = DB::table('account_client_receipts')
            ->where('id', $receiptId)
            ->where('receipt_type', 1)
            ->first();

        if (!$receipt) {
            abort(404, 'Client fund receipt not found.');
        }

        $resolvedClientMatterId = $this->resolveClientMatterIdForReceiptFix($request, $receipt->client_id);
        if ($resolvedClientMatterId === null) {
            abort(422, 'Provide the target matter: matter=PSA_1 (or matter_no=), or client_matter_id as numeric id or short code (client_unique_matter_no).');
        }

        DB::table('account_client_receipts')
            ->where('id', $receiptId)
            ->where('receipt_type', 1)
            ->update(['client_matter_id' => $resolvedClientMatterId]);

        Log::info('Client fund receipt matter fixed and PDF queued for regenerate', [
            'receipt_id' => $receiptId,
            'client_matter_id' => $resolvedClientMatterId,
            'admin_id' => Auth::guard('admin')->id(),
        ]);

        $query = ['regenerate' => '1'];
        if ($request->boolean('download')) {
            $query['download'] = '1';
        }

        return redirect()->to(url('/clients/genClientFundReceipt/' . $receiptId . '?' . http_build_query($query)));
    }

    /**
     * Resolve client_matters.id from matter short code or numeric id (scoped to receipt's client).
     * Priority: matter / matter_no query → else client_matter_id (digits = id, else = client_unique_matter_no).
     *
     * @param  int|string  $receiptClientId  admins.id for the client
     * @return int|null Missing matter params
     */
    private function resolveClientMatterIdForReceiptFix(Request $request, $receiptClientId): ?int
    {
        $receiptClientId = (int) $receiptClientId;
        $shortCode = null;
        if ($request->filled('matter')) {
            $shortCode = trim((string) $request->query('matter'));
        } elseif ($request->filled('matter_no')) {
            $shortCode = trim((string) $request->query('matter_no'));
        }

        if ($shortCode !== null && $shortCode !== '') {
            $matter = DB::table('client_matters')
                ->where('client_id', $receiptClientId)
                ->where('client_unique_matter_no', $shortCode)
                ->first();
            if (!$matter) {
                abort(404, 'No matter found for this client with short code (client_unique_matter_no): ' . $shortCode);
            }

            return (int) $matter->id;
        }

        if (!$request->filled('client_matter_id')) {
            return null;
        }

        $raw = trim((string) $request->query('client_matter_id'));
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw) && (int) $raw > 0) {
            $matter = DB::table('client_matters')
                ->where('id', (int) $raw)
                ->where('client_id', $receiptClientId)
                ->first();
            if (!$matter) {
                abort(403, 'client_matter_id must refer to a matter belonging to the same client as this receipt.');
            }

            return (int) $matter->id;
        }

        $matter = DB::table('client_matters')
            ->where('client_id', $receiptClientId)
            ->where('client_unique_matter_no', $raw)
            ->first();
        if (!$matter) {
            abort(404, 'No matter found for this client with client_unique_matter_no: ' . $raw);
        }

        return (int) $matter->id;
    }

public function genofficereceiptInvoice(Request $request, $id){
    $record_get = DB::table('account_client_receipts')->where('receipt_type',2)->where('id',$id)->first();
    // Validate receipt exists
    if (!$record_get) {
        abort(404, 'Receipt not found');
    }
    
    // ============= START CACHING LOGIC =============
    
    // Check if PDF already exists in AWS
    if (!empty($record_get->pdf_document_id)) {
        $existingPdf = DB::table('documents')
         ->where('id', $record_get->pdf_document_id)
         ->first();
        
        if ($existingPdf && !empty($existingPdf->myfile)) {
         // PDF exists in AWS, return it
         if ($request->has('download')) {
             // Force download with proper headers
             $headers = [
                 'Content-Type' => 'application/pdf',
                 'Content-Disposition' => 'attachment; filename="' . $existingPdf->file_name . '"',
             ];
             return redirect()->away($existingPdf->myfile)->withHeaders($headers);
         } else {
             // Stream in browser
             return redirect()->away($existingPdf->myfile);
         }
        }
    }
    
    // ============= PDF DATA PREPARATION =============
    
    $clientname = DB::table('admins')->where('id',$record_get->client_id)->first();
    
    // Validate client exists
    if (!$clientname) {
        abort(404, 'Client not found');
    }
    
    // Get client's current address from client_addresses table
    $clientAddress = DB::table('client_addresses')
        ->where('client_id', $record_get->client_id)
        ->where('is_current', 1)
        ->first();
    
    // If no current address, get the most recent one
    if (!$clientAddress) {
        $clientAddress = DB::table('client_addresses')
         ->where('client_id', $record_get->client_id)
         ->orderByRaw(\App\Models\ClientAddress::ORDER_BY_DISPLAY_SQL)
         ->orderByDesc('id')
         ->first();
    }
    
    // Merge address data into clientname object
    if ($clientAddress) {
        $clientname->address = $clientAddress->address_line_1 ?? $clientAddress->address ?? '';
        if (!empty($clientAddress->address_line_2)) {
         $clientname->address .= (!empty($clientname->address) ? ', ' : '') . $clientAddress->address_line_2;
        }
        $clientname->city = $clientAddress->suburb ?? '';
        $clientname->state = $clientAddress->state ?? '';
        $clientname->zip = $clientAddress->zip ?? '';
        $clientname->country = $clientAddress->country ?? '';
    }

    //Get client matter
    if( !empty($record_get) && !empty($record_get->client_matter_id)) {
        $client_matter_no = '';
        $client_matter_name = '';
        $client_matter_display = '';
        
        $client_info = DB::table('admins')->select('client_id')->where('id',$record_get->client_id)->first();
        if($client_info){
         $client_unique_id = $client_info->client_id; } else {
         $client_unique_id = '';
        }

        $matter_info = DB::table('client_matters')
         ->join('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
         ->select('client_matters.client_unique_matter_no', 'matters.title as matter_name', 'matters.nick_name')
         ->where('client_matters.id', $record_get->client_matter_id)
         ->first();
        
        if($matter_info){
         $client_unique_matter_no = $matter_info->client_unique_matter_no;
         $client_matter_no = $client_unique_id.'-'.$client_unique_matter_no;
         
         // Use full title (matter_name)
         $client_matter_name = $matter_info->matter_name ?? '';
         
         // Create display string with both name and number
         if (!empty($client_matter_name)) {
             $client_matter_display = $client_matter_name . ' (' . $client_matter_no . ')';
         } else {
             $client_matter_display = $client_matter_no;
         }
        } else {
         $client_unique_matter_no = '';
         $client_matter_no = '';
         $client_matter_display = '';
        }
    } else {
        $client_matter_no = '';
        $client_matter_display = '';
    }

    try {
        // Generate PDF if it doesn't exist or was deleted
        $pdf = PDF::setOptions([
         'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
         'logOutputFile' => storage_path('logs/log.htm'),
         'tempDir' => storage_path('logs/')
        ])->loadView('emails.genofficereceipt',compact(['record_get','clientname','client_matter_no','client_matter_display']));
        
        // Save PDF to AWS S3
        $pdfContent = $pdf->output();
        $fileName = 'Office-Receipt-' . ($record_get->trans_no ?? $id) . '.pdf';
        $client_unique_id = $clientname->client_id ?? 'unknown';
        $docType = 'office_receipts'; // Category for S3 storage
        $s3FileName = time() . '_' . uniqid() . '_' . $fileName;
        $filePath = $client_unique_id . '/' . $docType . '/' . $s3FileName;
        
        // Upload to S3
        Storage::disk('s3')->put($filePath, $pdfContent);
        $s3Url = $this->s3PublicUrl($filePath);
        
        // Get authenticated user ID
        $userId = Auth::check() ? Auth::user()->id : 1;
        
        // Save document reference in database
        $document = new \App\Models\Document;
        $document->file_name = $fileName;
        $document->filetype = 'pdf';
        $document->user_id = $userId;
        $document->myfile = $s3Url;
        $document->myfile_key = $s3FileName;
        $document->client_id = $record_get->client_id;
        $document->type = 'office_receipt'; // Document type identifier
        $document->doc_type = $docType;
        $document->file_size = strlen($pdfContent);
        $document->save();
        
        // Update account_client_receipts with PDF document ID
        DB::table('account_client_receipts')
         ->where('id', $id)
         ->update(['pdf_document_id' => $document->id]);
        
        // Return appropriate response
        if ($request->has('download')) {
         // Force download
         $headers = [
             'Content-Type' => 'application/pdf',
             'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
         ];
         return redirect()->away($s3Url)->withHeaders($headers);
        } else {
         // Stream in browser
         return redirect()->away($s3Url);
        }
        
    } catch (\Exception $e) {
        Log::error('PDF Generation/Upload Error: ' . $e->getMessage(), [
         'office_receipt_id' => $id,
         'trace' => $e->getTraceAsString()
        ]);
        
        // Fall back to direct PDF generation
        $pdf = PDF::setOptions([
         'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
         'logOutputFile' => storage_path('logs/log.htm'),
         'tempDir' => storage_path('logs/')
        ])->loadView('emails.genofficereceipt',compact(['record_get','clientname','client_matter_no','client_matter_display']));
        
        return $pdf->stream('Office-Receipt-' . ($record_get->trans_no ?? $id) . '.pdf');
    }
    
    // ============= END CACHING LOGIC =============
}

/*public function updateClientFundsLedger(Request $request)
{);
    $requestData = $request->all();
    $id = $request->input('id');
    $trans_date = $request->input('trans_date');
    $entry_date = $request->input('entry_date');
    $client_fund_ledger_type = $request->input('client_fund_ledger_type');
    $description = $request->input('description');
    $deposit_amount = floatval($request->input('deposit_amount', 0));
    $withdraw_amount = floatval($request->input('withdraw_amount', 0));

    // Handle document upload
    $insertedDocId = null;
    $doc_saved = false;
    $client_unique_id = "";
    $awsUrl = "";
    $doctype = isset($request->doctype) ? $request->doctype : '';

    if ($request->hasfile('document_upload')) {
        $files = is_array($request->file('document_upload')) ? $request->file('document_upload') : [$request->file('document_upload')];

        $client_info = \App\Models\Admin::select('client_id')->where('id', $requestData['client_id'])->first();
        $client_unique_id = !empty($client_info) ? $client_info->client_id : "";

        foreach ($files as $file) {
         $size = $file->getSize();
         $fileName = $file->getClientOriginalName();
         $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
         $fileExtension = $file->getClientOriginalExtension();
         $name = time() . $file->getClientOriginalName();
         $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
         Storage::disk('s3')->put($filePath, file_get_contents($file));

         $obj = new \App\Models\Document;
         $obj->file_name = $nameWithoutExtension;
         $obj->filetype = $fileExtension;
         $obj->user_id = Auth::user()->id;
         $obj->myfile = $this->s3PublicUrl($filePath);
         $obj->myfile_key = $name;
         $obj->client_id = $requestData['client_id'];
         $obj->type = $request->type;
         $obj->file_size = $size;
         $obj->doc_type = $doctype;
         $doc_saved = $obj->save();
         $insertedDocId = $obj->id;
        }
    } else {
        $insertedDocId = null;
        $doc_saved = "";
    }

    // Validate that the entry is not a Fee Transfer
    $entry = DB::table('account_client_receipts')
        ->where('id', $id)
        ->where('receipt_type', 1)
        ->first();

    if (!$entry) {
        return response()->json([
         'status' => false,
         'message' => 'Entry not found.',
        ], 404);
    }

    if ($entry->client_fund_ledger_type === 'Fee Transfer') {
        return response()->json([
         'status' => false,
         'message' => 'Fee Transfer entries cannot be edited.',
        ], 403);
    }

    // Update the entry (excluding trans_no)
    $updated = DB::table('account_client_receipts')
        ->where('id', $id)
        ->update([
         'trans_date' => $trans_date,
         'entry_date' => $entry_date,
         'client_fund_ledger_type' => $client_fund_ledger_type,
         'description' => $description,
         'deposit_amount' => $deposit_amount,
         'withdraw_amount' => $withdraw_amount,
         'updated_at' => now(),
        ]);

    if ($updated) {
        // Delete cached PDF since receipt was updated
        if (!empty($entry->pdf_document_id)) {
         $pdfDoc = DB::table('documents')->where('id', $entry->pdf_document_id)->first();
         if ($pdfDoc && !empty($pdfDoc->myfile_key)) {
             // Delete from S3
             $client_unique_id = DB::table('admins')->where('id', $entry->client_id)->value('client_id');
             if ($client_unique_id) {
                 $s3Path = $client_unique_id . '/receipts/' . $pdfDoc->myfile_key;
                 try {
                     Storage::disk('s3')->delete($s3Path);
                 } catch (\Exception $e) {
                     Log::warning('Failed to delete PDF from S3: ' . $e->getMessage(), ['path' => $s3Path]);
                 }
             }
         }
         // Delete document record
         DB::table('documents')->where('id', $entry->pdf_document_id)->delete();
         // Clear PDF reference
         DB::table('account_client_receipts')
             ->where('id', $id)
             ->update(['pdf_document_id' => null]);
        }
        
        // Recalculate balances for all entries
        $entries = DB::table('account_client_receipts')
         ->where('client_id', $entry->client_id)
         ->where('receipt_type', 1)
         ->orderBy('id', 'asc')
         ->get();

        $running_balance = 0;
        $updatedEntries = [];

        foreach ($entries as $entry) {
         $running_balance += floatval($entry->deposit_amount) - floatval($entry->withdraw_amount);
         DB::table('account_client_receipts')
             ->where('id', $entry->id)
             ->update(['balance_amount' => $running_balance]);

         $entry->balance_amount = $running_balance;
         $updatedEntries[] = $entry;
        }

        // Log activity
        $subject = "updated client funds ledger entry. Reference no- {$entry->trans_no}";
        $activity = new \App\Models\ActivitiesLog;
        $activity->client_id = $entry->client_id;
        $activity->created_by = Auth::guard('admin')->id();
        $activity->description = '';
        $activity->subject = $subject;
        $activity->activity_type = 'financial';
        $activity->task_status = 0;
        $activity->pin = 0;
        $activity->save();

        return response()->json([
         'status' => true,
         'message' => 'Entry updated successfully.',
         'updatedEntries' => $updatedEntries,
         'currentFundsHeld' => $running_balance,
        ], 200);
    }

    return response()->json([
        'status' => false,
        'message' => 'Failed to update entry.',
    ], 500);
}*/

public function updateClientFundsLedger(Request $request)
{
    $requestData = $request->all();
    $id = $request->input('id');
    $trans_date = $request->input('trans_date');
    $entry_date = $request->input('entry_date');
    $client_fund_ledger_type = $request->input('client_fund_ledger_type');
    $description = $request->input('description');
    $payment_method = trim((string) $request->input('payment_method', ''));

    // Handle document upload
    $insertedDocId = null; // Use null to indicate no document uploaded
    $client_unique_id = "";
    $doctype = isset($request->doctype) ? $request->doctype : '';

    if ($request->hasFile('document_upload')) {
        $files = is_array($request->file('document_upload')) ? $request->file('document_upload') : [$request->file('document_upload')];

        $client_info = \App\Models\Admin::select('client_id')->where('id', $requestData['client_id'])->first();
        $client_unique_id = !empty($client_info) ? $client_info->client_id : "";

        foreach ($files as $file) {
         $size = $file->getSize();
         $fileName = $file->getClientOriginalName();
         $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
         $fileExtension = $file->getClientOriginalExtension();
         $name = time() . $file->getClientOriginalName();
         $filePath = $client_unique_id . '/' . $doctype . '/' . $name;
         Storage::disk('s3')->put($filePath, file_get_contents($file));

         $obj = new \App\Models\Document;
         $obj->file_name = $nameWithoutExtension;
         $obj->filetype = $fileExtension;
         $obj->user_id = Auth::user()->id;
         $obj->myfile = $this->s3PublicUrl($filePath);
         $obj->myfile_key = $name;
         $obj->client_id = $requestData['client_id'];
         $obj->type = $request->type;
         $obj->file_size = $size;
         $obj->doc_type = $doctype;
         $obj->save();

         $insertedDocId = $obj->id; // Store the last inserted ID
        }
    }

    // Validate that the entry is not a Fee Transfer
    $entry = DB::table('account_client_receipts')
        ->where('id', $id)
        ->where('receipt_type', 1)
        ->first();

    if (!$entry) {
        return response()->json([
         'status' => false,
         'message' => 'Entry not found.',
        ], 404);
    }

    if ($entry->client_fund_ledger_type === 'Fee Transfer') {
        return response()->json([
         'status' => false,
         'message' => 'Fee Transfer entries cannot be edited.',
        ], 403);
    }

    $principal = floatval($request->input('deposit_amount', 0));
    $withdraw_amount = floatval($request->input('withdraw_amount', 0));
    $surchargeInput = max(0, floatval($request->input('eftpos_surcharge_amount', 0)));
    $eftposSurcharge = ($client_fund_ledger_type === 'Deposit' && $payment_method === 'EFTPOS') ? round($surchargeInput, 2) : 0.0;
    $deposit_amount = ($client_fund_ledger_type === 'Deposit') ? round($principal + $eftposSurcharge, 2) : $principal;

    // Prepare the update data for account_client_receipts
    $updateData = [
        'trans_date' => $trans_date,
        'entry_date' => $entry_date,
        'client_fund_ledger_type' => $client_fund_ledger_type,
        'description' => $description,
        'deposit_amount' => $deposit_amount,
        'withdraw_amount' => $withdraw_amount,
        'payment_method' => $payment_method,
        'eftpos_surcharge_amount' => $eftposSurcharge > 0 ? $eftposSurcharge : null,
        'updated_at' => now(),
    ];

    // If a document was uploaded, add the uploaded_doc_id to the update data
    if ($insertedDocId !== null) {
        $updateData['uploaded_doc_id'] = $insertedDocId;
    }

    // Update the entry in account_client_receipts (excluding trans_no)
    $updated = DB::table('account_client_receipts')
        ->where('id', $id)
        ->update($updateData);

    if ($updated) {
        // Recalculate balances for all entries
        $entries = DB::table('account_client_receipts')
         ->where('client_id', $entry->client_id)
         ->where('receipt_type', 1)
         ->orderBy('id', 'asc')
         ->get();

        $running_balance = 0;
        $updatedEntries = [];

        foreach ($entries as $ledgerRow) {
         $running_balance += floatval($ledgerRow->deposit_amount) - floatval($ledgerRow->withdraw_amount);
         DB::table('account_client_receipts')
             ->where('id', $ledgerRow->id)
             ->update(['balance_amount' => $running_balance]);

         $ledgerRow->balance_amount = $running_balance;
         $updatedEntries[] = $ledgerRow;
        }

        // Log activity
        /** @var \App\Models\Admin|null $staff */
        $staff = Auth::guard('admin')->user();
        $userName = $staff ? trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? '')) : 'Staff';
        $transactionType = $deposit_amount > 0 ? 'Deposit' : 'Withdrawal';
        $amount = $deposit_amount > 0 ? $deposit_amount : $withdraw_amount;
        $formattedAmount = '$' . number_format($amount, 2);
        $formattedBalance = '$' . number_format($running_balance, 2);
        $transDate = date('d/m/Y', strtotime($trans_date));
        
        $subject = "Client Funds Ledger Updated - {$formattedAmount} (Ref: {$entry->trans_no})";
        
        $descriptionText = "<div class='activity-detail'>";
        $descriptionText .= "<p><strong>{$userName}</strong> updated a client funds ledger entry:</p>";
        $descriptionText .= "<ul>";
        $descriptionText .= "<li><strong>Type:</strong> {$transactionType}</li>";
        $descriptionText .= "<li><strong>Amount:</strong> {$formattedAmount}</li>";
        $descriptionText .= "<li><strong>Transaction Date:</strong> {$transDate}</li>";
        $descriptionText .= "<li><strong>Reference No:</strong> {$entry->trans_no}</li>";
        if (!empty($description)) {
            $descriptionText .= "<li><strong>Description:</strong> " . htmlspecialchars($description) . "</li>";
        }
        $descriptionText .= "<li><strong>Updated Balance:</strong> {$formattedBalance}</li>";
        if ($insertedDocId !== null) {
            $descriptionText .= "<li><strong>Document:</strong> Attached</li>";
        }
        $descriptionText .= "</ul>";
        $descriptionText .= "</div>";
        
        $activity = new \App\Models\ActivitiesLog;
        $activity->client_id = $entry->client_id;
        $activity->created_by = Auth::guard('admin')->id();
        $activity->description = $descriptionText;
        $activity->subject = $subject;
        $activity->activity_type = 'financial';
        $activity->task_status = 0;
        $activity->pin = 0;
        $activity->save();

        return response()->json([
         'status' => true,
         'message' => 'Entry updated successfully.',
         'updatedEntries' => $updatedEntries,
         'currentFundsHeld' => $running_balance,
        ], 200);
    }

    return response()->json([
        'status' => false,
        'message' => 'Failed to update entry.',
    ], 500);
}

public function getInvoiceAmount(Request $request)
{
    // Validate the request
    $request->validate([
        'invoice_no' => 'required|string',
    ]);

    $row = AccountClientReceipt::select('client_id')
        ->where('invoice_no', $request->invoice_no)
        ->where('receipt_type', 3)
        ->first();
    if ($row) {
        $pending = app(InvoicePaymentSyncService::class)->computePaymentState((int) $row->client_id, $request->invoice_no);

        return response()->json([
         'success' => true,
         'balance_amount' => $pending['new_balance'] ?? 0,
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Invoice not found',
        'balance_amount' => 0,
    ]);
}
   /**
     * Send invoice to Hubdoc
     */
    /**
     * ================================================================
     * HUBDOC INTEGRATION
     * ================================================================
     */

    public function sendToHubdoc(Request $request, $id)
    {
        try {
         // Increase execution time limit for PDF generation
         set_time_limit(300); // 5 minutes
         
         // Check if invoice exists
         $record_get = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->get();
         
         if(empty($record_get) || count($record_get) == 0) {
             return response()->json([
                 'status' => false,
                 'message' => 'Invoice not found'
             ]);
         }

         // Get client info
         $clientname = DB::table('admins')->where('id',$record_get[0]->client_id)->first();
         
         // Calculate amounts
         $total_Invoice_Amount = AccountAllInvoiceReceipt::where('receipt_type', 3)
             ->where('receipt_id', $id)
             ->sum(DB::raw("CASE
                 WHEN payment_type = 'Discount' THEN -withdraw_amount
                 ELSE withdraw_amount
             END"));

         // Get all required data for PDF generation
         $record_get_Professional_Fee_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Professional Fee')->count();
         $record_get_Department_Charges_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Department Charges')->count();
         $record_get_Surcharge_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Surcharge')->count();
         $record_get_Disbursements_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Disbursements')->count();
         $record_get_Other_Cost_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Other Cost')->count();
         $record_get_Discount_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Discount')->count();

         $record_get_Professional_Fee = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Professional Fee')->get();
         $record_get_Department_Charges = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Department Charges')->get();
         $record_get_Surcharge = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Surcharge')->get();
         $record_get_Disbursements = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Disbursements')->get();
         $record_get_Other_Cost = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Other Cost')->get();
         $record_get_Discount = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$id)->where('payment_type','Discount')->get();

         $total_Gross_Amount = AccountAllInvoiceReceipt::where('receipt_type', 3)
             ->where('receipt_id', $id)
             ->sum(DB::raw("
                 CASE
                     WHEN payment_type = 'Discount' AND gst_included = 'Yes' THEN -(withdraw_amount - (withdraw_amount / 11))
                     WHEN payment_type = 'Discount' AND gst_included = 'No' THEN -withdraw_amount
                     WHEN gst_included = 'Yes' THEN withdraw_amount - (withdraw_amount / 11)
                     ELSE withdraw_amount
                 END
             "));

         $total_GST_amount =  $total_Invoice_Amount - $total_Gross_Amount;

         // FIX: Handle NULL values for amounts (prevent decimal casting errors in Blade template)
         if ($total_Gross_Amount === null) {
             $total_Gross_Amount = 0.00;
         }
         if ($total_Invoice_Amount === null) {
             $total_Invoice_Amount = 0.00;
         }
         if ($total_GST_amount === null) {
             $total_GST_amount = 0.00;
         }

         $hubdocAcr = DB::table('account_client_receipts')
             ->where('receipt_type', 3)
             ->where('receipt_id', $id)
             ->where('client_id', $record_get[0]->client_id)
             ->orderBy('id')
             ->first();
         $voidHubdoc = $hubdocAcr && isset($hubdocAcr->void_invoice) ? (int) $hubdocAcr->void_invoice : null;

         $invoicePaymentSyncHubdoc = app(InvoicePaymentSyncService::class);
         $total_Pending_amount = $invoicePaymentSyncHubdoc->pendingAmountForReceiptPdf(
             (int) $record_get[0]->client_id,
             (int) $id,
             (float) $total_Invoice_Amount,
             $voidHubdoc === 1 ? 1 : null
         );

         if ($total_Pending_amount === null) {
             $total_Pending_amount = 0.00;
         }

         // Get payment method
         $invoice_payment_method = '';
         if( !empty($record_get) && $record_get[0]->invoice_no != '') {
             $office_receipt = DB::table('account_client_receipts')->select('payment_method')->where('receipt_type',2)->where('invoice_no',$record_get[0]->invoice_no)->first();
             if($office_receipt){
                 $invoice_payment_method = $office_receipt->payment_method;
             }
         }

        // Get client matter
        $client_matter_no = '';
        $client_matter_display = '';
         if( !empty($record_get) && $record_get[0]->client_matter_id != '') {
             $client_info = DB::table('admins')->select('client_id')->where('id',$record_get[0]->client_id)->first();
             if($client_info){
                 $client_unique_id = $client_info->client_id;
             } else {
                 $client_unique_id = '';
             }

            $matter_info = DB::table('client_matters')
                ->join('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
                ->select('client_matters.client_unique_matter_no', 'matters.title as matter_name')
                ->where('client_matters.id',$record_get[0]->client_matter_id)
                ->first();
             if($matter_info){
                 $client_unique_matter_no = $matter_info->client_unique_matter_no;
                 $client_matter_no = $client_unique_id.'-'.$client_unique_matter_no;
                $client_matter_name = $matter_info->matter_name ?? '';

                if (!empty($client_matter_name)) {
                    $client_matter_display = $client_matter_name . ' (' . $client_matter_no . ')';
                } else {
                    $client_matter_display = $client_matter_no;
                }
             } else {
                 $client_unique_matter_no = '';
                 $client_matter_no = '';
                $client_matter_display = '';
             }
        } else {
            $client_matter_display = '';
         }

         // Generate PDF with optimized settings
         $pdf = PDF::setOptions([
             'isHtml5ParserEnabled' => true, 
             'isRemoteEnabled' => true,
             'logOutputFile' => storage_path('logs/log.htm'),
             'tempDir' => storage_path('logs/'),
             'chroot' => storage_path('logs/'),
             'enable_php' => false,
             'enable_javascript' => false,
             'enable_smart_shrinking' => true,
             'dpi' => 96,
             'default_font' => 'Arial'
         ])->loadView('emails.geninvoice',compact(
             ['record_get',
             'record_get_Professional_Fee_cnt',
             'record_get_Department_Charges_cnt',
             'record_get_Surcharge_cnt',
             'record_get_Disbursements_cnt',
             'record_get_Other_Cost_cnt',
             'record_get_Discount_cnt',

             'record_get_Professional_Fee',
             'record_get_Department_Charges',
             'record_get_Surcharge',
             'record_get_Disbursements',
             'record_get_Other_Cost',
             'record_get_Discount',

             'total_Gross_Amount',
             'total_Invoice_Amount',
             'total_GST_amount',
             'total_Pending_amount',

             'clientname',
             'invoice_payment_method',
             'client_matter_no',
             'client_matter_display'
         ]));

         // Save PDF to temporary file
         $pdfFileName = 'Invoice_' . $record_get[0]->invoice_no . '_' . time() . '.pdf';
         $pdfPath = storage_path('app/temp/' . $pdfFileName);
         
         // Create temp directory if it doesn't exist
         if (!file_exists(storage_path('app/temp'))) {
             mkdir(storage_path('app/temp'), 0755, true);
         }
         
         $pdf->save($pdfPath);

         // Prepare email data
         $invoiceData = [
             'invoice_no' => $record_get[0]->invoice_no ?? 'N/A',
             'client_name' => $clientname->name ?? 'N/A',
             'invoice_date' => $record_get[0]->trans_date ?? 'N/A',
             'amount' => $total_Invoice_Amount,
             'pdf_path' => $pdfPath,
             'file_name' => $pdfFileName
         ];

        $hubdocEmail = env('HUBDOC_EMAIL', 'bansalcrm11@gmail.com');
        app(SystemEmailLogService::class)->logAndSendMailable([
            'category'  => 'hubdoc',
            'from_mail' => config('mail.from.address'),
            'to_mail'   => $hubdocEmail,
            'subject'   => 'Invoice for Hubdoc Processing',
        ], new HubdocInvoiceMail($invoiceData), $hubdocEmail);

         // Mark invoice as sent to Hubdoc
         $hubdocSentAt = now();
         $updateResult = DB::table('account_client_receipts')
             ->where('receipt_type', 3)
             ->where('receipt_id', $id)
             ->update([
                 'hubdoc_sent' => true,
                 'hubdoc_sent_at' => $hubdocSentAt
             ]);

         // Clean up temporary file
         if (file_exists($pdfPath)) {
             unlink($pdfPath);
         }

         return response()->json([
             'status' => true,
             'message' => 'Invoice sent to Hubdoc successfully!',
             'hubdoc_sent' => true,
             'hubdoc_sent_at' => $hubdocSentAt->toIso8601String(),
             'hubdoc_sent_at_formatted' => $hubdocSentAt->format('d/m/Y H:i'),
         ]);

        } catch (\Exception $e) {
         // Clean up any temporary files in case of error
         if (isset($pdfPath) && file_exists($pdfPath)) {
             unlink($pdfPath);
         }
         
         return response()->json([
             'status' => false,
             'message' => 'Error sending invoice to Hubdoc: ' . $e->getMessage()
         ]);
        }
    }

    /**
     * Check Hubdoc status for an invoice
     */
    public function checkHubdocStatus(Request $request, $id)
    {
        try {
         $hubdoc_status = DB::table('account_client_receipts')
             ->where('receipt_type', 3)
             ->where('receipt_id', $id)
             ->select('hubdoc_sent', 'hubdoc_sent_at')
             ->first();

         if ($hubdoc_status) {
             $hubdocSentAtFormatted = $hubdoc_status->hubdoc_sent_at
                 ? \Carbon\Carbon::parse($hubdoc_status->hubdoc_sent_at)->format('d/m/Y H:i')
                 : null;

             return response()->json([
                 'hubdoc_sent' => (bool) $hubdoc_status->hubdoc_sent,
                 'hubdoc_sent_at' => $hubdoc_status->hubdoc_sent_at,
                 'hubdoc_sent_at_formatted' => $hubdocSentAtFormatted,
             ]);
         } else {
             return response()->json([
                 'hubdoc_sent' => false,
                 'hubdoc_sent_at' => null,
                 'hubdoc_sent_at_formatted' => null,
             ]);
         }

        } catch (\Exception $e) {
         return response()->json([
             'hubdoc_sent' => false,
             'hubdoc_sent_at' => null,
             'error' => $e->getMessage()
         ]);
        }
    }

    /**
     * Send Invoice to Client Portal (Client Portal / Mobile App)
     * Sends push notification to the client, sets client_portal_sent=1 and client_portal_sent_at, logs the activity.
     */
    public function sendInvoiceToClientApplication(Request $request, $id)
    {
        try {
            // Get invoice record
            $record_get = AccountAllInvoiceReceipt::where('receipt_type', 3)
                ->where('receipt_id', $id)
                ->first();

            if (!$record_get) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            // Only allow for Pending (Unpaid) invoices - invoice_status 0 = Unpaid
            $receipt_entry = DB::table('account_client_receipts')
                ->where('receipt_id', $id)
                ->where('receipt_type', 3)
                ->first();

            if (!$receipt_entry) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice receipt not found'
                ], 404);
            }

            if (isset($receipt_entry->invoice_status) && $receipt_entry->invoice_status != 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice must be Pending (Unpaid) to send to Client Portal'
                ], 400);
            }

            // Check if already sent
            if (!empty($receipt_entry->client_portal_sent)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice has already been sent to Client Portal'
                ], 400);
            }

            // Get client info (client_id in record is the admin id for client portal users)
            $client = DB::table('admins')
                ->where('id', $record_get->client_id)
                ->first();

            if (!$client) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $invoiceNo = $record_get->invoice_no ?? $record_get->trans_no ?? 'N/A';

            // Send push notification to client's mobile app (if they have the app with FCM token)
            try {
                $fcmService = new FCMService();
                $title = 'New Invoice Available';
                $body = 'Invoice #' . $invoiceNo . ' has been sent to your Client Portal.';
                $data = [
                    'type' => 'invoice',
                    'invoice_id' => (string) $id,
                    'invoice_no' => $invoiceNo,
                ];
                $fcmService->sendToUser($record_get->client_id, $title, $body, $data);
            } catch (\Exception $e) {
                // Log but don't fail - client may not have the app installed
                Log::info('FCM notification skipped or failed for invoice', [
                    'invoice_id' => $id,
                    'client_id' => $record_get->client_id,
                    'message' => $e->getMessage()
                ]);
            }

            // Update client_portal_sent = 1 and client_portal_sent_at = now
            DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->where('receipt_id', $id)
                ->update([
                    'client_portal_sent' => 1,
                    'client_portal_sent_at' => now()
                ]);

            // Insert in-app notification (visible in client portal notification center)
            $clientMatterId = $receipt_entry->client_matter_id ?? $record_get->client_matter_id ?? null;
            $notificationMessage = 'Invoice #' . $invoiceNo . ' has been sent to your Client Portal.';
            DB::table('notifications')->insert([
                'sender_id' => Auth::user()->id,
                'receiver_id' => $record_get->client_id,
                'module_id' => $clientMatterId,
                'url' => '/invoices',
                'notification_type' => 'invoice_sent_to_client_app',
                'message' => $notificationMessage,
                'created_at' => now(),
                'updated_at' => now(),
                'sender_status' => 1,
                'receiver_status' => 0,
                'seen' => 0,
            ]);

            // Log activity
            $objs = new ActivitiesLog;
            $objs->client_id = $record_get->client_id;
            $objs->created_by = Auth::user()->id;
            $objs->description = 'Invoice #' . $invoiceNo . ' sent to Client Portal';
            $objs->subject = 'Invoice sent to Client Portal';
            $objs->activity_type = 'financial';
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();

            // Create Client Portal action note(s) for PR + PA; fallback assignee = current user.
            $clientMatterId = $receipt_entry->client_matter_id ?? $record_get->client_matter_id ?? null;
            $clientMatter = $clientMatterId ? ClientMatter::find($clientMatterId) : null;
            $matterIdForNote = $clientMatterId !== null && $clientMatterId !== '' ? (int) $clientMatterId : null;
            ClientPortalActionNoteService::createGroupedForMatter(
                (int) $record_get->client_id,
                $matterIdForNote,
                'Invoice #' . $invoiceNo . ' sent to Client Portal',
                (int) Auth::user()->id,
                $clientMatter,
                (int) Auth::user()->id,
                (int) Auth::user()->id
            );

            return response()->json([
                'status' => true,
                'message' => 'Invoice sent to Client Portal successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending invoice to client portal: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to send: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ================================================================
     * SEND TO CLIENT FUNCTIONALITY
     * ================================================================
     */

    /**
     * Send Invoice to Client via Email
     */
    public function sendInvoiceToClient(Request $request, $id)
    {
        try {
            // Get invoice record
            $record_get = AccountAllInvoiceReceipt::where('receipt_type', 3)
                ->where('receipt_id', $id)
                ->first();

            if (!$record_get) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            // Get receipt entry
            $receipt_entry = DB::table('account_client_receipts')
                ->where('receipt_id', $id)
                ->where('receipt_type', 3)
                ->first();

            if (!$receipt_entry) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invoice receipt not found'
                ], 404);
            }

            // Get client info
            $clientname = DB::table('admins')
                ->where('id', $record_get->client_id)
                ->first();

            // Use client email (primary_email column removed Phase 4)
            $clientEmail = $clientname ? ($clientname->email ?? null) : null;

            if (!$clientname || empty($clientEmail)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client email not found'
                ], 404);
            }

            // Generate PDF (reuse existing logic from genInvoice)
            $invoiceUrl = url('/clients/genInvoice/' . $id);
            
            // Get or generate PDF
            $pdfUrl = null;
            if (!empty($receipt_entry->pdf_document_id)) {
                $existingPdf = DB::table('documents')
                    ->where('id', $receipt_entry->pdf_document_id)
                    ->first();
                
                if ($existingPdf && !empty($existingPdf->myfile)) {
                    $pdfUrl = $existingPdf->myfile;
                }
            }

            if (!$pdfUrl) {
                // Generate PDF if not exists
                $genRequest = new Request();
                $response = $this->genInvoice($genRequest, $id);
                
                // Get the newly created PDF
                $receipt_entry = DB::table('account_client_receipts')
                    ->where('receipt_id', $id)
                    ->where('receipt_type', 3)
                    ->first();
                
                if (!empty($receipt_entry->pdf_document_id)) {
                    $existingPdf = DB::table('documents')
                        ->where('id', $receipt_entry->pdf_document_id)
                        ->first();
                    
                    if ($existingPdf && !empty($existingPdf->myfile)) {
                        $pdfUrl = $existingPdf->myfile;
                    }
                }
            }

            if (!$pdfUrl) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to generate PDF'
                ], 500);
            }

            // Prepare email data
            $clientFullName = trim(($clientname->first_name ?? '') . ' ' . ($clientname->last_name ?? ''));
            $invoiceNo = $receipt_entry->invoice_no ?? $id;
            
            $subject = 'Invoice #' . $invoiceNo . ' from Bansal Immigration';
            $emailContent = "Dear " . $clientFullName . ",<br><br>" .
                "Please find attached your invoice #" . $invoiceNo . ".<br><br>" .
                "If you have any questions, please don't hesitate to contact us.<br><br>" .
                "Best regards,<br>Bansal Immigration";

            $pdfContent = file_get_contents($pdfUrl);
            if ($pdfContent === false || $pdfContent === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to download invoice PDF'
                ], 500);
            }

            // Send email using InvoiceEmailManager (in-memory attachment — no temp file)
            $invoiceArray = [
                'view' => 'emails.template',
                'from' => 'invoice@bansalimmigration.com.au',
                'name' => 'Bansal Immigration',
                'subject' => $subject,
                'file_content' => $pdfContent,
                'file_name' => 'Invoice-' . $invoiceNo . '.pdf',
                'content' => $emailContent
            ];

            $systemEmailLog = app(SystemEmailLogService::class);
            $log = $systemEmailLog->createPending([
                'category'  => 'invoice',
                'from_mail' => 'invoice@bansalimmigration.com.au',
                'to_mail'   => $clientEmail,
                'subject'   => $subject,
                'message'   => $emailContent,
                'client_id' => $record_get->client_id,
                'user_id'   => Auth::user()->id,
            ]);
            $invoiceArray['email_log_id'] = $log->id;

            Mail::mailer('sendgrid')->to($clientEmail)->queue(new \App\Mail\InvoiceEmailManager($invoiceArray));

            // Log activity
            $objs = new ActivitiesLog;
            $objs->client_id = $record_get->client_id;
            $objs->created_by = Auth::user()->id;
            $objs->description = 'Invoice #' . $invoiceNo . ' sent to client email: ' . $clientEmail;
            $objs->subject = 'Invoice sent to client';
            $objs->activity_type = 'financial';
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();

            return response()->json([
                'status' => true,
                'message' => 'Invoice sent successfully to ' . $clientEmail
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending invoice to client: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to send invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send Client Fund Receipt to Client via Email
     */
    public function sendClientFundReceiptToClient(Request $request, $id)
    {
        try {
            // Get receipt record
            $record_get = DB::table('account_client_receipts')
                ->where('receipt_type', 1)
                ->where('id', $id)
                ->first();

            if (!$record_get) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client fund receipt not found'
                ], 404);
            }

            // Get client info
            $clientname = DB::table('admins')
                ->where('id', $record_get->client_id)
                ->first();

            // Use client email (primary_email column removed Phase 4)
            $clientEmail = $clientname ? ($clientname->email ?? null) : null;

            if (!$clientname || empty($clientEmail)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client email not found'
                ], 404);
            }

            // Get or generate PDF
            $pdfUrl = null;
            if (!empty($record_get->pdf_document_id)) {
                $existingPdf = DB::table('documents')
                    ->where('id', $record_get->pdf_document_id)
                    ->first();
                
                if ($existingPdf && !empty($existingPdf->myfile)) {
                    $pdfUrl = $existingPdf->myfile;
                }
            }

            if (!$pdfUrl) {
                // Generate PDF if not exists
                $genRequest = new Request();
                $response = $this->genClientFundReceipt($genRequest, $id);
                
                // Get the newly created PDF
                $record_get = DB::table('account_client_receipts')
                    ->where('receipt_type', 1)
                    ->where('id', $id)
                    ->first();
                
                if (!empty($record_get->pdf_document_id)) {
                    $existingPdf = DB::table('documents')
                        ->where('id', $record_get->pdf_document_id)
                        ->first();
                    
                    if ($existingPdf && !empty($existingPdf->myfile)) {
                        $pdfUrl = $existingPdf->myfile;
                    }
                }
            }

            if (!$pdfUrl) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to generate PDF'
                ], 500);
            }

            // Prepare email data
            $clientFullName = trim(($clientname->first_name ?? '') . ' ' . ($clientname->last_name ?? ''));
            $receiptNo = $record_get->trans_no ?? $id;
            
            $subject = 'Client Fund Receipt #' . $receiptNo . ' from Bansal Immigration';
            $emailContent = "Dear " . $clientFullName . ",<br><br>" .
                "Please find attached your client fund receipt #" . $receiptNo . ".<br><br>" .
                "If you have any questions, please don't hesitate to contact us.<br><br>" .
                "Best regards,<br>Bansal Immigration";

            $pdfContent = file_get_contents($pdfUrl);
            if ($pdfContent === false || $pdfContent === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to download receipt PDF'
                ], 500);
            }

            // Send email using InvoiceEmailManager (in-memory attachment — no temp file)
            $invoiceArray = [
                'view' => 'emails.template',
                'from' => 'invoice@bansalimmigration.com.au',
                'name' => 'Bansal Immigration',
                'subject' => $subject,
                'file_content' => $pdfContent,
                'file_name' => 'Receipt-' . $receiptNo . '.pdf',
                'content' => $emailContent
            ];

            $systemEmailLog = app(SystemEmailLogService::class);
            $log = $systemEmailLog->createPending([
                'category'  => 'receipt',
                'from_mail' => 'invoice@bansalimmigration.com.au',
                'to_mail'   => $clientEmail,
                'subject'   => $subject,
                'message'   => $emailContent,
                'client_id' => $record_get->client_id,
                'user_id'   => Auth::user()->id,
            ]);
            $invoiceArray['email_log_id'] = $log->id;

            Mail::mailer('sendgrid')->to($clientEmail)->queue(new \App\Mail\InvoiceEmailManager($invoiceArray));

            // Log activity
            $objs = new ActivitiesLog;
            $objs->client_id = $record_get->client_id;
            $objs->created_by = Auth::user()->id;
            $objs->description = 'Client fund receipt #' . $receiptNo . ' sent to client email: ' . $clientEmail;
            $objs->subject = 'Client fund receipt sent to client';
            $objs->activity_type = 'financial';
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();

            return response()->json([
                'status' => true,
                'message' => 'Client fund receipt sent successfully to ' . $clientEmail
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending client fund receipt to client: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to send receipt: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send Office Receipt to Client via Email
     */
    public function sendOfficeReceiptToClient(Request $request, $id)
    {
        try {
            // Get receipt record
            $record_get = DB::table('account_client_receipts')
                ->where('receipt_type', 2)
                ->where('id', $id)
                ->first();

            if (!$record_get) {
                return response()->json([
                    'status' => false,
                    'message' => 'Office receipt not found'
                ], 404);
            }

            // Get client info
            $clientname = DB::table('admins')
                ->where('id', $record_get->client_id)
                ->first();

            // Use client email (primary_email column removed Phase 4)
            $clientEmail = $clientname ? ($clientname->email ?? null) : null;

            if (!$clientname || empty($clientEmail)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client email not found'
                ], 404);
            }

            // Get or generate PDF
            $pdfUrl = null;
            if (!empty($record_get->pdf_document_id)) {
                $existingPdf = DB::table('documents')
                    ->where('id', $record_get->pdf_document_id)
                    ->first();
                
                if ($existingPdf && !empty($existingPdf->myfile)) {
                    $pdfUrl = $existingPdf->myfile;
                }
            }

            if (!$pdfUrl) {
                // Generate PDF if not exists
                $genRequest = new Request();
                $response = $this->genofficereceiptInvoice($genRequest, $id);
                
                // Get the newly created PDF
                $record_get = DB::table('account_client_receipts')
                    ->where('receipt_type', 2)
                    ->where('id', $id)
                    ->first();
                
                if (!empty($record_get->pdf_document_id)) {
                    $existingPdf = DB::table('documents')
                        ->where('id', $record_get->pdf_document_id)
                        ->first();
                    
                    if ($existingPdf && !empty($existingPdf->myfile)) {
                        $pdfUrl = $existingPdf->myfile;
                    }
                }
            }

            if (!$pdfUrl) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to generate PDF'
                ], 500);
            }

            // Prepare email data
            $clientFullName = trim(($clientname->first_name ?? '') . ' ' . ($clientname->last_name ?? ''));
            $receiptNo = $record_get->trans_no ?? $id;
            
            $subject = 'Office Receipt #' . $receiptNo . ' from Bansal Immigration';
            $emailContent = "Dear " . $clientFullName . ",<br><br>" .
                "Please find attached your office receipt #" . $receiptNo . ".<br><br>" .
                "If you have any questions, please don't hesitate to contact us.<br><br>" .
                "Best regards,<br>Bansal Immigration";

            $pdfContent = file_get_contents($pdfUrl);
            if ($pdfContent === false || $pdfContent === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to download receipt PDF'
                ], 500);
            }

            // Send email using InvoiceEmailManager (in-memory attachment — no temp file)
            $invoiceArray = [
                'view' => 'emails.template',
                'from' => 'invoice@bansalimmigration.com.au',
                'name' => 'Bansal Immigration',
                'subject' => $subject,
                'file_content' => $pdfContent,
                'file_name' => 'Office-Receipt-' . $receiptNo . '.pdf',
                'content' => $emailContent
            ];

            $systemEmailLog = app(SystemEmailLogService::class);
            $log = $systemEmailLog->createPending([
                'category'  => 'receipt',
                'from_mail' => 'invoice@bansalimmigration.com.au',
                'to_mail'   => $clientEmail,
                'subject'   => $subject,
                'message'   => $emailContent,
                'client_id' => $record_get->client_id,
                'user_id'   => Auth::user()->id,
            ]);
            $invoiceArray['email_log_id'] = $log->id;

            Mail::mailer('sendgrid')->to($clientEmail)->queue(new \App\Mail\InvoiceEmailManager($invoiceArray));

            // Log activity
            $objs = new ActivitiesLog;
            $objs->client_id = $record_get->client_id;
            $objs->created_by = Auth::user()->id;
            $objs->description = 'Office receipt #' . $receiptNo . ' sent to client email: ' . $clientEmail;
            $objs->subject = 'Office receipt sent to client';
            $objs->activity_type = 'financial';
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();

            return response()->json([
                'status' => true,
                'message' => 'Office receipt sent successfully to ' . $clientEmail
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending office receipt to client: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to send receipt: ' . $e->getMessage()
            ], 500);
        }
    }

}
