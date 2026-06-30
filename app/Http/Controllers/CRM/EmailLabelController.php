<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use App\Models\EmailLabel;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailLabelController extends Controller
{
    use EnsuresCrmRecordAccess;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Get all labels (system + staff's custom)
     */
    public function index()
    {
        try {
            $labels = EmailLabel::where(function($query) {
                $query->where('user_id', Auth::id())
                      ->orWhereNull('user_id'); // System labels
            })
            ->active()
            ->orderBy('type', 'desc') // System first
            ->orderBy('name')
            ->get();

            return response()->json([
                'success' => true,
                'labels' => $labels
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch labels', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch labels'
            ], 500);
        }
    }

    /**
     * Create custom label
     */
    public function store(Request $request)
    {
        try {
            $userId = Auth::id();
            
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) use ($userId) {
                        // Check if label name already exists for this staff member
                        $exists = EmailLabel::where('user_id', $userId)
                            ->where('name', $value)
                            ->where('is_active', true)
                            ->exists();
                        
                        if ($exists) {
                            $fail('A label with this name already exists.');
                        }
                    }
                ],
                'color' => [
                    'required',
                    'string',
                    'regex:/^#[0-9A-Fa-f]{6}$/'
                ],
                'icon' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $label = EmailLabel::create([
                'user_id' => $userId,
                'name' => $request->name,
                'color' => $request->color,
                'type' => 'custom',
                'icon' => $request->icon ?? 'tag',
                'description' => $request->description,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Label created successfully',
                'label' => $label
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create label', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create label: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply label to email
     */
    public function apply(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mail_report_id' => 'required|exists:email_logs,id',
                'label_id' => 'required|exists:email_labels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $emailLog = EmailLog::findOrFail($request->mail_report_id);
            
            // Check if already attached
            if (!$emailLog->labels()->where('email_label_id', $request->label_id)->exists()) {
                $emailLog->labels()->attach($request->label_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Label applied successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to apply label', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply label'
            ], 500);
        }
    }

    /**
     * Remove label from email
     */
    public function remove(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mail_report_id' => 'required|exists:email_logs,id',
                'label_id' => 'required|exists:email_labels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $emailLog = EmailLog::findOrFail($request->mail_report_id);
            $this->ensureCrmRecordAccessForOptionalClientId(
                $emailLog->client_id ? (int) $emailLog->client_id : null
            );
            $emailLog->labels()->detach($request->label_id);

            return response()->json([
                'success' => true,
                'message' => 'Label removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove label', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove label'
            ], 500);
        }
    }
}
