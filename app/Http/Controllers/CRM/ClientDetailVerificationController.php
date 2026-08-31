<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\AcceptClientDetailVerificationChangeRequest;
use App\Models\Admin;
use App\Models\ClientDetailVerificationField;
use App\Services\ClientDetailVerificationService;
use App\Support\ClientDetailVerificationFields;
use App\Support\ClientDetailVerificationUi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ClientDetailVerificationController extends Controller
{
    use EnsuresCrmRecordAccess;

    public function send(Request $request, ClientDetailVerificationService $service): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'min:1'],
        ]);

        $clientId = (int) $validated['client_id'];
        $this->ensureCrmRecordAccessStrict($clientId);

        $client = Admin::query()
            ->where('id', $clientId)
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $client) {
            abort(404);
        }

        $result = $service->sendLink($client, Auth::guard('admin')->id());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function accept(
        AcceptClientDetailVerificationChangeRequest $request,
        ClientDetailVerificationField $field,
        ClientDetailVerificationService $service,
    ): JsonResponse {
        $this->ensureCrmRecordAccessStrict((int) $field->client_id);

        try {
            $updated = $service->acceptChange($field, (int) Auth::guard('admin')->id());
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Unable to accept this change.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Updated value has been finalized.',
            'field_key' => $updated->field_key,
            'status' => $updated->status,
            'display_value' => $updated->requested_value,
            'confirmed_icon' => ClientDetailVerificationUi::icon([
                'status' => ClientDetailVerificationFields::STATUS_ACCEPTED,
                'field_key' => $updated->field_key,
            ]),
        ]);
    }
}
