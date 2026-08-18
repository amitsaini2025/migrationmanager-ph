<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Services\MergeClientRecordsService;
use App\Services\Sms\UnifiedSmsManager;
use App\Traits\ClientAgreements;
use App\Traits\ClientAppointments;
use App\Traits\ClientAuthorization;
use App\Traits\ClientCostAssignments;
use App\Traits\ClientCrmFollowups;
use App\Traits\ClientHelpers;
use App\Traits\ClientQueries;
use App\Traits\CreatesClients;
use App\Traits\LogsClientActivity;
use GuzzleHttp\Client;

/**
 * @mixin Controller
 */
class ClientsController extends Controller
{
    use ClientAgreements;
    use ClientAppointments;
    use ClientAuthorization;
    use ClientCostAssignments;
    use ClientCrmFollowups;
    use ClientHelpers;
    use ClientQueries;
    use CreatesClients;
    use LogsClientActivity;

    protected Client $openAiClient;

    /** @var bool|null Cached for the current request only */
    protected $googleReviewCrmTemplateExistsCache = null;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected UnifiedSmsManager $smsManager,
        protected MergeClientRecordsService $mergeClientRecords,
    ) {
        /** @disregard P1013 */
        $this->middleware('auth:admin');

        /** @disregard P1010 */
        $openAiApiKey = config('services.openai.api_key');
        $this->openAiClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer '.$openAiApiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
