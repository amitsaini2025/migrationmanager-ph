<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SubmitClientDetailVerificationRequest;
use App\Services\ClientDetailVerificationService;
use Illuminate\View\View;

class PublicClientDetailVerificationController extends Controller
{
    public function show(string $token, ClientDetailVerificationService $service): View
    {
        $verification = $service->findUsableByToken($token);
        if (! $verification) {
            return view('public.client_detail_verification_expired');
        }

        $snapshot = is_array($verification->snapshot) ? $verification->snapshot : [];
        $client = $verification->client;

        return view('public.client_detail_verification', [
            'token' => $token,
            'submitted' => false,
            'firstName' => $client?->first_name ?: 'there',
            'values' => $snapshot,
            'submitUrl' => route('public.client-detail-verification.submit', ['token' => $token]),
        ]);
    }

    public function submit(
        SubmitClientDetailVerificationRequest $request,
        string $token,
        ClientDetailVerificationService $service,
    ): View {
        $verification = $service->findUsableByToken($token);
        if (! $verification) {
            return view('public.client_detail_verification_expired');
        }

        $service->submit(
            $verification,
            $request->validated('fields'),
            $request->ip(),
            $request->userAgent(),
        );

        $changed = collect($request->validated('fields'))
            ->where('status', 'change_requested')
            ->count();

        return view('public.client_detail_verification', [
            'token' => $token,
            'submitted' => true,
            'changedCount' => $changed,
            'firstName' => $verification->client?->first_name ?: 'there',
            'values' => is_array($verification->snapshot) ? $verification->snapshot : [],
            'submitUrl' => route('public.client-detail-verification.submit', ['token' => $token]),
        ]);
    }
}
