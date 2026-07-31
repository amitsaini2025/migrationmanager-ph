<?php

namespace App\Http\Controllers\AdminConsole\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

/**
 * SmsWebhookController
 *
 * Handles webhooks from SMS providers (Twilio, Cellcast) for AdminConsole
 * Used for delivery status updates and incoming messages
 */
class SmsWebhookController extends Controller
{
    /**
     * Handle Twilio webhook for delivery status
     */
    public function twilioStatus(Request $request)
    {
        if (!$this->validateTwilioRequest($request)) {
            Log::warning('Twilio status webhook rejected - invalid signature');
            return response('Forbidden', 403);
        }

        Log::info('Twilio Status Webhook', $request->all());

        $messageSid = $request->input('MessageSid');
        $status = $request->input('MessageStatus');

        if (!$messageSid || !$status) {
            return response('Invalid webhook data', 400);
        }

        // Update SMS log
        $smsLog = SmsLog::where('provider_message_id', $messageSid)->first();

        if ($smsLog) {
            $smsLog->update([
                'status' => $status,
                'delivered_at' => in_array($status, ['delivered']) ? now() : null,
            ]);

            Log::info('SMS status updated', [
                'sms_log_id' => $smsLog->id,
                'status' => $status
            ]);
        }

        return response('OK', 200);
    }

    /**
     * Handle Twilio webhook for incoming messages
     */
    public function twilioIncoming(Request $request)
    {
        if (!$this->validateTwilioRequest($request)) {
            Log::warning('Twilio incoming webhook rejected - invalid signature');
            return response('Forbidden', 403);
        }

        Log::info('Twilio Incoming Message', $request->all());

        // TODO: Implement incoming message handling in future sprints
        // Could be used for:
        // - Client responses
        // - Auto-reply system
        // - Keyword-based actions

        return response('OK', 200);
    }

    /**
     * Handle Cellcast webhook for delivery status
     */
    public function cellcastStatus(Request $request)
    {
        if (!$this->validateCellcastRequest($request)) {
            Log::warning('Cellcast status webhook rejected - invalid Basic Auth');
            return response('Forbidden', 403);
        }

        Log::info('Cellcast Status Webhook', $request->all());

        $messageId = $request->input('message_id') ?? $request->input('_id');
        $status = $request->input('status');

        if (!$messageId || !$status) {
            return response('Invalid webhook data', 400);
        }

        // Update SMS log
        $smsLog = SmsLog::where('provider_message_id', $messageId)->first();

        if ($smsLog) {
            // Map Cellcast status to internal status
            $internalStatus = $this->mapCellcastStatus($status);

            $smsLog->update([
                'status' => $internalStatus,
                'delivered_at' => in_array($internalStatus, ['delivered']) ? now() : null,
            ]);

            Log::info('SMS status updated', [
                'sms_log_id' => $smsLog->id,
                'status' => $internalStatus
            ]);
        }

        return response('OK', 200);
    }

    /**
     * Handle Cellcast webhook for incoming messages
     */
    public function cellcastIncoming(Request $request)
    {
        if (!$this->validateCellcastRequest($request)) {
            Log::warning('Cellcast incoming webhook rejected - invalid Basic Auth');
            return response('Forbidden', 403);
        }

        Log::info('Cellcast Incoming Message', $request->all());

        // TODO: Implement incoming message handling in future sprints

        return response('OK', 200);
    }

    /**
     * Validate Twilio X-Twilio-Signature.
     * When auth_token is not configured, accept (non-breaking) and log a warning.
     */
    protected function validateTwilioRequest(Request $request): bool
    {
        $authToken = config('services.twilio.auth_token');

        if (empty($authToken)) {
            Log::warning('Twilio webhook accepted without signature check - auth_token not configured');
            return true;
        }

        $signature = $request->header('X-Twilio-Signature', '');
        if ($signature === '') {
            return false;
        }

        $validator = new RequestValidator($authToken);
        $params = $request->post();

        // Prefer the request URL Twilio actually hit (proxy-aware when TrustProxies is set).
        if ($validator->validate($signature, $request->fullUrl(), $params)) {
            return true;
        }

        // Fallback: APP_URL + path (covers common reverse-proxy host/scheme mismatches).
        $appUrl = rtrim((string) config('app.url'), '/') . $request->getRequestUri();
        if ($appUrl !== $request->fullUrl() && $validator->validate($signature, $appUrl, $params)) {
            return true;
        }

        return false;
    }

    /**
     * Validate Cellcast webhook Basic Auth (username/password from Cellcast dashboard).
     * When credentials are not configured, accept (non-breaking) and log a warning.
     */
    protected function validateCellcastRequest(Request $request): bool
    {
        $username = config('services.cellcast.webhook_username');
        $password = config('services.cellcast.webhook_password');

        if ($username === null || $username === '' || $password === null || $password === '') {
            Log::warning('Cellcast webhook accepted without Basic Auth check - credentials not configured');
            return true;
        }

        $providedUser = (string) $request->getUser();
        $providedPass = (string) $request->getPassword();

        return hash_equals((string) $username, $providedUser)
            && hash_equals((string) $password, $providedPass);
    }

    /**
     * Map Cellcast status to internal status
     */
    protected function mapCellcastStatus($cellcastStatus)
    {
        $statusMap = [
            'SENT' => 'sent',
            'DELIVERED' => 'delivered',
            'FAILED' => 'failed',
            'REJECTED' => 'failed',
            'EXPIRED' => 'failed',
            'DEAD' => 'failed',
            'RECEIVED' => 'delivered',
        ];

        return $statusMap[strtoupper($cellcastStatus)] ?? 'unknown';
    }
}
