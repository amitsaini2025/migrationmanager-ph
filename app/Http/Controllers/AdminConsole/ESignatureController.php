<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\SignatureAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;

class ESignatureController extends Controller
{
    protected $analyticsService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(SignatureAnalyticsService $analyticsService)
    {
        $this->middleware('auth:admin');
        $this->analyticsService = $analyticsService;
    }

    /**
     * Display the E-Signature management dashboard.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Date range filtering
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $medianHours = $this->analyticsService->getMedianTimeToSign();
        $completionRate = $this->analyticsService->getCompletionRate($startDate, $endDate);
        $avgReminders = $this->analyticsService->getAverageReminders($startDate, $endDate);
        $overdueCount = $this->analyticsService->getOverdueCount();
        $topSigners = $this->analyticsService->getTopSigners(10);
        $documentTypeStats = $this->analyticsService->getDocumentTypeStats();
        $trendData = $this->analyticsService->getSignatureTrend($startDate, $endDate, 'day');
        $overdueDocuments = $this->analyticsService->getOverdueAnalytics();

        $userPerformance = null;
        if ((int) $user->role === 1) {
            $userPerformance = $this->analyticsService->getUserPerformance();
        }

        $errors = ($request->hasSession() ? $request->session()->get('errors') : null) ?? new MessageBag;

        return view('AdminConsole.features.esignature.index', compact(
            'medianHours',
            'completionRate',
            'avgReminders',
            'overdueCount',
            'topSigners',
            'documentTypeStats',
            'trendData',
            'overdueDocuments',
            'userPerformance',
            'startDate',
            'endDate',
            'user',
            'errors'
        ));
    }

    /**
     * Export audit report
     */
    public function exportAudit(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = Document::query()
            ->forSignatureWorkflow()
            ->notArchived()
            ->select('id', 'file_name', 'status', 'created_by', 'client_id', 'lead_id', 'created_at')
            ->with([
                'creator:id,first_name,last_name',
                'signers:id,document_id,email,name,status,signed_at,reminder_count',
                'client:id,first_name,last_name',
                'lead:id,first_name,last_name',
            ]);

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        if ($request->format === 'csv') {
            return $this->exportCSV($documents);
        } else {
            return $this->exportPDF($documents);
        }
    }

    /**
     * Export as CSV
     */
    protected function exportCSV($documents)
    {
        $filename = 'signature_audit_'.date('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($documents) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Document ID',
                'Title',
                'Status',
                'Created By',
                'Created At',
                'Signer Email',
                'Signer Name',
                'Signer Status',
                'Sent At',
                'Signed At',
                'Reminders Sent',
                'Document Type',
                'Priority',
                'Associated With',
                'Due Date',
            ]);

            // Data rows
            foreach ($documents as $doc) {
                foreach ($doc->signers as $signer) {
                    $association = 'Ad-hoc';
                    $entity = $doc->client_id ? $doc->client : ($doc->lead_id ? $doc->lead : null);
                    if ($entity) {
                        $type = $doc->lead_id ? 'Lead' : 'Admin';
                        $name = trim(($entity->first_name ?? '').' '.($entity->last_name ?? ''));
                        $association = "{$type}: {$name}";
                    }

                    fputcsv($file, [
                        $doc->id,
                        $doc->display_title,
                        $doc->status,
                        $doc->creator ? $doc->creator->first_name.' '.$doc->creator->last_name : 'Unknown',
                        $doc->created_at->format('Y-m-d H:i:s'),
                        $signer->email,
                        $signer->name,
                        $signer->status,
                        $doc->created_at->format('Y-m-d H:i:s'),
                        $signer->signed_at ? $signer->signed_at->format('Y-m-d H:i:s') : 'N/A',
                        $signer->reminder_count,
                        'general',
                        'normal',
                        $association,
                        'N/A',
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export as PDF
     */
    protected function exportPDF($documents)
    {
        // For now, redirect to CSV export
        // PDF export can be implemented later if needed
        return $this->exportCSV($documents);
    }
}
