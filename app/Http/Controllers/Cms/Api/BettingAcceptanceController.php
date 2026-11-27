<?php

namespace App\Http\Controllers\Cms\Api;

use App\Betting\Acceptance\AcceptanceService;
use App\Betting\AdapterVps\AdapterVpsFailureGuard;
use App\Betting\AdapterVps\AdapterVpsQuoteAcceptanceService;
use App\Betting\Application\Acceptances\AcceptanceApiErrorMapper;
use App\Betting\Application\Api\BettingApiResponder;
use App\Betting\Exceptions\AdapterVpsAcceptanceException;
use App\Betting\Exceptions\PublicSelectionTokenException;
use App\Betting\Exceptions\QuoteSelectionMismatchException;
use App\Betting\SocialMarkets\SocialMarketAcceptanceService;
use App\Betting\Support\BettingActivityLogService;
use App\Betting\Support\PublicSelectionTokenService;
use App\Betting\Support\QuoteRefRedactor;
use App\Http\Controllers\Controller;
use App\Models\BettingAcceptance;
use App\Models\BettingActivityLog;
use App\Models\BettingQuoteSelection;
use App\Services\OpsRadar\OpsRadarEventEmitter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class BettingAcceptanceController extends Controller
{
    private $acceptanceService;
    private $errorMapper;
    private $responder;
    private $activityLogService;
    private $adapterVpsAcceptanceService;
    private $adapterVpsFailureGuard;
    private $quoteRefRedactor;
    private $selectionTokenService;
    private $socialMarketAcceptanceService;

    public function __construct(
        ?AcceptanceService $acceptanceService = null,
        ?AdapterVpsQuoteAcceptanceService $adapterVpsAcceptanceService = null,
        ?AdapterVpsFailureGuard $adapterVpsFailureGuard = null,
        ?AcceptanceApiErrorMapper $errorMapper = null,
        ?BettingApiResponder $responder = null,
        ?BettingActivityLogService $activityLogService = null,
        ?QuoteRefRedactor $quoteRefRedactor = null,
        ?PublicSelectionTokenService $selectionTokenService = null,
        ?SocialMarketAcceptanceService $socialMarketAcceptanceService = null
    ) {
        $this->acceptanceService = $acceptanceService ?: app(AcceptanceService::class);
        $this->adapterVpsAcceptanceService = $adapterVpsAcceptanceService ?: app(AdapterVpsQuoteAcceptanceService::class);
        $this->adapterVpsFailureGuard = $adapterVpsFailureGuard ?: app(AdapterVpsFailureGuard::class);
        $this->errorMapper = $errorMapper ?: app(AcceptanceApiErrorMapper::class);
        $this->responder = $responder ?: app(BettingApiResponder::class);
        $this->activityLogService = $activityLogService ?: app(BettingActivityLogService::class);
        $this->quoteRefRedactor = $quoteRefRedactor ?: app(QuoteRefRedactor::class);
        $this->selectionTokenService = $selectionTokenService ?: app(PublicSelectionTokenService::class);
        $this->socialMarketAcceptanceService = $socialMarketAcceptanceService;
    }

    public function store(Request $request)
    {
        if ($blocked = $this->rejectOfflineDemo($request)) return $blocked;
        $requestId = $this->resolveRequestId($request);

        if ((string) $request->input('selection_source', '') === 'social_market') {
            return $this->responder->error('UNTRANSFERRED_CAPABILITY','Social Market transfer is not complete.',409);
            return $this->storeSocialMarketSelection($request, $requestId);
        }

        if (trim((string) $request->input('quote_ref', '')) !== ''
            || trim((string) $request->input('selection_token', '')) !== ''
            || trim((string) $request->input('client_selection_ref', '')) !== ''
        ) {
            return $this->storeAdapterVpsQuoteRef($request, $requestId);
        }

        $validator = Validator::make($request->all(), [
            'selection_source' => ['nullable', 'string', 'in:social_feed'],
            'quote_version_id' => ['required', 'integer'],
            'quote_selection_id' => ['required', 'integer'],
            'stake_amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'max:5'],
            'accepted_price' => ['nullable', 'numeric', 'gt:0'],
        ]);

        if ($validator->fails()) {
            return $this->responder->error(
                'VALIDATION_ERROR',
                'Acceptance request is invalid.',
                422,
                ['fields' => $validator->errors()->toArray()]
            );
        }

        $payload = $validator->validated();
        $selection = BettingQuoteSelection::query()
            ->with(['quoteVersion.event', 'market', 'outcome'])
            ->find($payload['quote_selection_id']);

        if (!$selection) {
            return $this->responder->error(
                'QUOTE_SELECTION_NOT_FOUND',
                'Selected quote selection could not be found.',
                404,
                ['quote_selection_id' => $payload['quote_selection_id']]
            );
        }

        if ((int) $selection->betting_quote_version_id !== (int) $payload['quote_version_id']) {
            $exception = new QuoteSelectionMismatchException(
                (int) $payload['quote_version_id'],
                (int) $payload['quote_selection_id']
            );

            $this->logRejectedAttempt(
                $selection,
                $payload,
                trim((string) $request->header('Idempotency-Key', '')) ?: null,
                $exception->errorCode(),
                $exception->getMessage(),
                $request->attributes->get('cms_api_actor'),
                $requestId
            );

            $mapped = $this->errorMapper->map($exception);

            return $this->responder->error(
                $mapped['code'],
                $mapped['message'],
                $mapped['status'],
                $mapped['details']
            );
        }

        $actor = $request->attributes->get('cms_api_actor');
        $authMode = (string) $request->attributes->get('cms_api_auth_mode', 'session');
        $actorType = (string) $request->attributes->get('cms_api_actor_type', 'cms_user');
        $actorRole = $request->attributes->get('cms_api_actor_role') ?: optional($actor)->role;
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;
        $selectionSource = (string) ($payload['selection_source'] ?? 'sportsbook');
        $isSocialFeedSelection = $selectionSource === 'social_feed';

        try {
            $acceptance = $this->acceptanceService->requestForSelection($selection, [
                'stake_amount' => $payload['stake_amount'],
                'currency' => $payload['currency'],
                'accepted_price' => $payload['accepted_price'] ?? null,
                'cms_user_id' => optional($actor)->id,
                'idempotency_key' => $idempotencyKey,
                'acceptance_contract_version' => $isSocialFeedSelection ? 'social_feed_v1' : 'legacy_v1',
                'acceptance_source' => $isSocialFeedSelection ? 'social_feed' : 'cms_legacy',
                'context' => [
                    'request_source' => $isSocialFeedSelection ? 'cms_betting_api_social_feed' : 'cms_betting_api',
                    'selection_source' => $selectionSource,
                    'request_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $requestId,
                    'actor_role' => $actorRole,
                    'auth_mode' => $authMode,
                    'actor_type' => $actorType,
                ],
            ]);
        } catch (RuntimeException $exception) {
            \Illuminate\Support\Facades\Log::warning('Sportsbook acceptance rejected.', ['request_id'=>$requestId,'exception_class'=>get_class($exception),'sqlstate'=>$exception instanceof \PDOException ? (string)$exception->getCode() : null,'driver_code'=>$exception instanceof \PDOException ? ($exception->errorInfo[1]??null) : null]);
            $mapped = $this->errorMapper->map($exception);
            $this->logRejectedAttempt($selection, $payload, $idempotencyKey, $mapped['code'], $mapped['message'], $actor, $requestId);
            $this->recordRiskLimitBlock($request, $mapped, $payload, $actor, 'betting_quote_selections', $selection->id);

            return $this->responder->error(
                $mapped['code'],
                $mapped['message'],
                $mapped['status'],
                array_merge($mapped['details'] ?? [], [
                    'quote_version_id' => (int) $payload['quote_version_id'],
                    'quote_selection_id' => (int) $payload['quote_selection_id'],
                    'idempotency_key' => $idempotencyKey,
                ])
            );
        }

        $acceptance->loadMissing(['event', 'quoteVersion', 'quoteSelection.outcome']);

        $this->activityLogService->logAcceptance(
            $acceptance,
            'acceptance.api_received',
            'Acceptance request received through betting API.',
            [
                'request_source' => $isSocialFeedSelection ? 'cms_betting_api_social_feed' : 'cms_betting_api',
                'request_ip' => $request->ip(),
                'actor_role' => $actorRole,
                'actor_type' => $actorType,
                'auth_mode' => $authMode,
                'idempotency_key' => $idempotencyKey,
                'request_id' => $requestId,
                'selection_source' => $selectionSource,
            ],
            $actor
        );

        return $this->responder->success([
            'acceptance' => $this->publicAcceptancePayload($acceptance, [
                'selection_source' => $selectionSource,
            ]),
        ], [
            'actor_role' => $actorRole,
            'actor_type' => $actorType,
            'auth_mode' => $authMode,
        ]);
    }

    private function storeSocialMarketSelection(Request $request, string $requestId)
    {
        $validator = Validator::make($request->all(), [
            'selection_source' => ['required', 'string', 'in:social_market'],
            'selection_token' => ['required', 'string', 'max:4096'],
            'stake_amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'max:5'],
            'accepted_price' => ['required', 'numeric', 'gt:0'],
            'quote_ref' => ['prohibited'],
            'source_provider' => ['prohibited'],
            'source_provider_key' => ['prohibited'],
            'provider_payload' => ['prohibited'],
        ]);

        if ($validator->fails()) {
            return $this->responder->error(
                'VALIDATION_ERROR',
                'Social market acceptance request is invalid.',
                422,
                ['fields' => $validator->errors()->toArray()]
            );
        }

        $payload = $validator->validated();
        $actor = $request->attributes->get('cms_api_actor');
        $authMode = (string) $request->attributes->get('cms_api_auth_mode', 'session');
        $actorType = (string) $request->attributes->get('cms_api_actor_type', 'cms_user');
        $actorRole = $request->attributes->get('cms_api_actor_role') ?: optional($actor)->role;
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;

        try {
            $acceptance = $this->socialMarketAcceptanceService->requestForToken((string) $payload['selection_token'], [
                'stake_amount' => $payload['stake_amount'],
                'currency' => $payload['currency'],
                'accepted_price' => $payload['accepted_price'],
                'cms_user_id' => optional($actor)->id,
                'idempotency_key' => $idempotencyKey,
                'context' => [
                    'request_source' => 'cms_betting_api_social_market',
                    'request_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $requestId,
                    'actor_role' => $actorRole,
                    'auth_mode' => $authMode,
                    'actor_type' => $actorType,
                ],
            ]);
        } catch (RuntimeException $exception) {
            \Illuminate\Support\Facades\Log::warning('Sportsbook acceptance rejected.', ['request_id'=>$requestId,'exception_class'=>get_class($exception),'sqlstate'=>$exception instanceof \PDOException ? (string)$exception->getCode() : null,'driver_code'=>$exception instanceof \PDOException ? ($exception->errorInfo[1]??null) : null]);
            $mapped = $this->errorMapper->map($exception);
            $this->recordRiskLimitBlock($request, $mapped, $payload, $actor);

            return $this->responder->error(
                $mapped['code'],
                $mapped['message'],
                $mapped['status'],
                array_merge($mapped['details'] ?? [], [
                    'idempotency_key' => $idempotencyKey,
                ])
            );
        }

        $this->activityLogService->logAcceptance(
            $acceptance,
            'acceptance.api_received',
            'Social market acceptance request received through betting API.',
            [
                'request_source' => 'cms_betting_api_social_market',
                'request_ip' => $request->ip(),
                'actor_role' => $actorRole,
                'actor_type' => $actorType,
                'auth_mode' => $authMode,
                'idempotency_key' => $idempotencyKey,
                'request_id' => $requestId,
                'selection_source' => 'social_market',
            ],
            $actor
        );

        return $this->responder->success([
            'acceptance' => $this->publicAcceptancePayload($acceptance, [
                'selection_source' => 'social_market',
            ]),
        ], [
            'actor_role' => $actorRole,
            'actor_type' => $actorType,
            'auth_mode' => $authMode,
        ]);
    }

    public function storeAdapterVpsQuoteRefEndpoint(Request $request)
    {
        return $this->storeAdapterVpsQuoteRef($request, $this->resolveRequestId($request));
    }

    private function storeAdapterVpsQuoteRef(Request $request, string $requestId)
    {
        if ($blocked = $this->rejectOfflineDemo($request)) return $blocked;
        $validator = Validator::make($request->all(), [
            'quote_ref' => ['nullable', 'string', 'max:191'],
            'selection_token' => ['nullable', 'string', 'max:8192'],
            'client_selection_ref' => ['nullable', 'string', 'max:8192'],
            'stake_amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'max:5'],
            'accepted_price' => ['nullable', 'numeric', 'gt:0'],
        ]);

        if ($validator->fails()) {
            return $this->responder->error(
                'VALIDATION_ERROR',
                'Adapter VPS acceptance request is invalid.',
                422,
                ['fields' => $validator->errors()->toArray()]
            );
        }

        $payload = $validator->validated();
        if (trim((string) ($payload['quote_ref'] ?? '')) === ''
            && trim((string) ($payload['selection_token'] ?? '')) === ''
            && trim((string) ($payload['client_selection_ref'] ?? '')) === ''
        ) {
            return $this->responder->error(
                'VALIDATION_ERROR',
                'Adapter VPS acceptance request is invalid.',
                422,
                ['fields' => ['selection_token' => ['A selection token is required.']]]
            );
        }

        $actor = $request->attributes->get('cms_api_actor');
        $authMode = (string) $request->attributes->get('cms_api_auth_mode', 'session');
        $actorType = (string) $request->attributes->get('cms_api_actor_type', 'cms_user');
        $actorRole = $request->attributes->get('cms_api_actor_role') ?: optional($actor)->role;
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;
        $rootUserId = optional($actor)->parent_id ?: optional($actor)->id;

        try {
            $payload = $this->resolveAdapterVpsSelectionPayload($payload);
        } catch (AdapterVpsAcceptanceException $exception) {
            $mapped = $this->errorMapper->map($exception);
            $supportCode = $this->logRejectedAdapterAttempt(
                $payload,
                $idempotencyKey,
                $mapped['code'],
                (string) data_get($mapped, 'details.reason_code', ''),
                $mapped['status'],
                $actor,
                $requestId,
                array_filter([
                    'selection_token_hash' => $this->selectionTokenHashFromPayload($payload),
                ])
            );
            $details = array_merge($mapped['details'] ?? [], [
                'support_code' => $supportCode,
                'idempotency_key' => $idempotencyKey,
            ]);
            unset($details['quote_ref']);

            return $this->responder->error(
                $mapped['code'],
                $mapped['message'],
                $mapped['status'],
                $details,
                ['support_code' => $supportCode]
            );
        }

        $guardContext = $this->adapterVpsFailureGuard->blockContext(
            $rootUserId ? (int) $rootUserId : null,
            $request->ip(),
            (string) $payload['quote_ref']
        );

        if ($guardContext !== null) {
            $exception = new AdapterVpsAcceptanceException(
                'ADAPTER_VPS_ATTEMPT_RATE_LIMITED',
                'Adapter acceptance attempts are temporarily limited.',
                429,
                [
                    'reason_code' => 'attempt_rate_limited',
                    'retry_after_seconds' => (int) ($guardContext['retry_after_seconds'] ?? 0),
                    'guard_scope' => (string) ($guardContext['scope'] ?? ''),
                ]
            );
            $mapped = $this->errorMapper->map($exception);
            $supportCode = $this->logRejectedAdapterAttempt(
                $payload,
                $idempotencyKey,
                $mapped['code'],
                (string) data_get($mapped, 'details.reason_code', 'attempt_rate_limited'),
                $mapped['status'],
                $actor,
                $requestId,
                [
                    'guard_scope' => (string) ($guardContext['scope'] ?? ''),
                    'retry_after_seconds' => (int) ($guardContext['retry_after_seconds'] ?? 0),
                ]
            );
            $details = array_merge($mapped['details'] ?? [], [
                'support_code' => $supportCode,
                'idempotency_key' => $idempotencyKey,
            ]);
            unset($details['quote_ref']);

            return $this->responder->error(
                $mapped['code'],
                $mapped['message'],
                $mapped['status'],
                $details,
                ['support_code' => $supportCode]
            );
        }

        try {
            $acceptance = $this->adapterVpsAcceptanceService->requestForQuoteRef((string) $payload['quote_ref'], [
                'stake_amount' => $payload['stake_amount'],
                'currency' => $payload['currency'],
                'accepted_price' => $payload['accepted_price'] ?? null,
                'cms_user_id' => optional($actor)->id,
                'idempotency_key' => $idempotencyKey,
                'selection_count' => 1,
                'is_accumulator' => false,
                'last_trusted_quote_issued_at' => (int) data_get($payload, '_selection_token_context.issued_at', 0),
                'last_trusted_quote' => (array) data_get($payload, '_selection_token_context.last_trusted_quote', []),
                'context' => array_merge([
                    'request_source' => 'cms_betting_api_adapter_vps',
                    'request_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_id' => $requestId,
                    'actor_role' => $actorRole,
                    'auth_mode' => $authMode,
                    'actor_type' => $actorType,
                ], $this->selectionTokenAcceptanceContext((array) ($payload['_selection_token_context'] ?? []))),
            ]);
        } catch (RuntimeException $exception) {
            \Illuminate\Support\Facades\Log::warning('Sportsbook acceptance rejected.', ['request_id'=>$requestId,'exception_class'=>get_class($exception),'sqlstate'=>$exception instanceof \PDOException ? (string)$exception->getCode() : null,'driver_code'=>$exception instanceof \PDOException ? ($exception->errorInfo[1]??null) : null]);
            $mapped = $this->errorMapper->map($exception);
            $this->recordRiskLimitBlock($request, $mapped, $payload, $actor);
            $this->adapterVpsFailureGuard->recordFailure(
                $rootUserId ? (int) $rootUserId : null,
                $request->ip(),
                (string) $payload['quote_ref']
            );
            $supportCode = $this->logRejectedAdapterAttempt(
                $payload,
                $idempotencyKey,
                $mapped['code'],
                (string) data_get($mapped, 'details.reason_code', ''),
                $mapped['status'],
                $actor,
                $requestId
            );
            $details = array_merge($mapped['details'] ?? [], [
                'support_code' => $supportCode,
                'idempotency_key' => $idempotencyKey,
            ]);
            unset($details['quote_ref']);

            return $this->responder->error(
                $mapped['code'],
                $mapped['message'],
                $mapped['status'],
                $details,
                ['support_code' => $supportCode]
            );
        }

        $this->activityLogService->logAcceptance(
            $acceptance,
            'acceptance.api_received',
            'Adapter VPS quote_ref acceptance request received through betting API.',
            [
                'request_source' => 'cms_betting_api_adapter_vps',
                'request_ip' => $request->ip(),
                'actor_role' => $actorRole,
                'actor_type' => $actorType,
                'auth_mode' => $authMode,
                'idempotency_key' => $idempotencyKey,
                'request_id' => $requestId,
                'quote_ref_hash' => $this->quoteRefRedactor->hash($acceptance->quote_ref),
            ],
            $actor
        );

        return $this->responder->success([
            'acceptance' => $this->publicAcceptancePayload($acceptance, [
                'selection_source' => 'sportsbook',
            ]),
        ], [
            'actor_role' => $actorRole,
            'actor_type' => $actorType,
            'auth_mode' => $authMode,
        ]);
    }

    private function resolveAdapterVpsSelectionPayload(array $payload): array
    {
        $quoteRef = trim((string) ($payload['quote_ref'] ?? ''));
        $token = trim((string) ($payload['selection_token'] ?? ($payload['client_selection_ref'] ?? '')));

        if ($token === '') {
            $payload['quote_ref'] = $quoteRef;

            return $payload;
        }

        try {
            $context = $this->selectionTokenService->resolve($token);
        } catch (PublicSelectionTokenException $exception) {
            throw new AdapterVpsAcceptanceException(
                'ADAPTER_VPS_VERIFY_INVALID',
                'Adapter VPS selection token is invalid.',
                $exception->httpStatus(),
                ['reason_code' => $exception->reasonCode()]
            );
        }

        $resolvedQuoteRef = trim((string) ($context['quote_ref'] ?? ''));
        if ((string) ($context['phase_key'] ?? '') !== 'pre-match') {
            throw new AdapterVpsAcceptanceException(
                'ADAPTER_VPS_VERIFY_INVALID',
                'Adapter VPS selection token phase is invalid.',
                409,
                ['reason_code' => 'selection_token_phase_invalid']
            );
        }

        if ($quoteRef !== '' && $quoteRef !== $resolvedQuoteRef) {
            throw new AdapterVpsAcceptanceException(
                'ADAPTER_VPS_VERIFY_INVALID',
                'Adapter VPS selection token quote mismatch.',
                409,
                ['reason_code' => 'selection_token_quote_mismatch']
            );
        }

        if (!$this->selectionTokenPriceMatches($payload, $context)) {
            throw new AdapterVpsAcceptanceException(
                'ADAPTER_VPS_VERIFY_INVALID',
                'Adapter VPS selection token price mismatch.',
                409,
                ['reason_code' => 'selection_token_price_mismatch']
            );
        }

        $payload['quote_ref'] = $resolvedQuoteRef;
        $payload['_selection_token_context'] = $context;

        return $payload;
    }

    private function publicAcceptancePayload(BettingAcceptance $acceptance, array $options = []): array
    {
        $snapshot = is_array($acceptance->accepted_snapshot_json)
            ? $acceptance->accepted_snapshot_json
            : [];

        $payload = [
            'acceptance_id' => $acceptance->id,
            'acceptance_ref' => $acceptance->acceptance_ref,
            'status' => $acceptance->status,
            'decision' => $acceptance->decision,
            'currency' => $acceptance->currency,
            'stake_amount' => (string) $acceptance->stake_amount,
            'accepted_price' => (string) $acceptance->accepted_price,
            'idempotency_key' => $acceptance->idempotency_key,
            'source' => [
                'selection_source' => (string) ($options['selection_source'] ?? 'sportsbook'),
            ],
            'selection' => [
                'quote_selection_id' => $acceptance->betting_quote_selection_id,
                'outcome_id' => $acceptance->betting_outcome_id,
                'outcome_code' => optional(optional($acceptance->quoteSelection)->outcome)->outcome_code,
                'display_name' => optional(optional($acceptance->quoteSelection)->outcome)->display_name
                    ?: data_get($snapshot, 'selection.display_name')
                    ?: data_get($snapshot, 'selection.label'),
                'decimal_price' => data_get($snapshot, 'selection.decimal_price'),
                'market_title' => data_get($snapshot, 'market.title'),
            ],
        ];

        if ($acceptance->event || $acceptance->betting_event_id) {
            $payload['event'] = [
                'event_id' => optional($acceptance->event)->id ?: $acceptance->betting_event_id,
                'event_key' => optional($acceptance->event)->event_key,
                'canonical_name' => optional($acceptance->event)->canonical_name,
                'starts_at' => optional(optional($acceptance->event)->starts_at)->toIso8601String(),
            ];
        }

        if ($acceptance->quoteVersion || $acceptance->betting_quote_version_id) {
            $payload['quote'] = [
                'quote_version_id' => $acceptance->betting_quote_version_id,
                'version_no' => optional($acceptance->quoteVersion)->version_no,
            ];
        }

        return $payload;
    }

    private function rejectOfflineDemo(Request $request)
    {
        if (app(\App\Betting\Application\Offers\CurrentOffersSnapshotStore::class)->demoActive()
            || $request->input('acceptance_mode') === 'offline_demo' || $request->boolean('offline_demo')) {
            return response()->json(['success' => false, 'data' => null,
                'error' => ['code' => 'OFFLINE_DEMO_READ_ONLY', 'message' => trans('cms_public.sportsbook.js.offline_demo_no_submit')]], 409);
        }
        return null;
    }

    private function selectionTokenPriceMatches(array $payload, array $context): bool
    {
        if (!array_key_exists('accepted_price', $payload) || $payload['accepted_price'] === null) {
            return true;
        }

        $tokenPrice = (float) ($context['decimal_price'] ?? 0);
        $acceptedPrice = (float) $payload['accepted_price'];

        if ($tokenPrice <= 0 || $acceptedPrice <= 0) {
            return true;
        }

        return abs($tokenPrice - $acceptedPrice) <= 0.000001;
    }

    private function selectionTokenAcceptanceContext(array $context): array
    {
        if ($context === []) {
            return [];
        }

        return [
            'selection_token_version' => (int) ($context['v'] ?? 0),
            'selection_token_issued_at' => (int) ($context['issued_at'] ?? 0),
            'selection_token_expires_at' => (int) ($context['expires_at'] ?? 0),
            'selection_token_phase_key' => (string) ($context['phase_key'] ?? ''),
            'selection_token_event_id' => (int) ($context['event_id'] ?? 0),
            'selection_token_market_type' => (string) ($context['market_type'] ?? ''),
            'selection_token_market_specifier_key' => (string) ($context['market_specifier_key'] ?? ''),
            'selection_token_outcome_code' => (string) ($context['outcome_code'] ?? ''),
        ];
    }

    private function selectionTokenHashFromPayload(array $payload): ?string
    {
        $token = trim((string) ($payload['selection_token'] ?? ($payload['client_selection_ref'] ?? '')));

        return $token !== '' ? hash('sha256', $token) : null;
    }

    private function logRejectedAdapterAttempt(
        array $payload,
        ?string $idempotencyKey,
        string $errorCode,
        string $reasonCode,
        int $httpStatus,
        $actor,
        string $requestId,
        array $extraMeta = []
    ): string {
        $supportCode = $requestId !== '' ? $requestId : (string) Str::uuid();
        $quoteRef = (string) ($payload['quote_ref'] ?? '');
        $meta = array_merge([
            'support_code' => $supportCode,
            'request_id' => $requestId,
            'endpoint' => 'adapter-vps acceptance',
            'cms_user_id' => optional($actor)->id,
            'root_user_id' => optional($actor)->parent_id ?: optional($actor)->id,
            'quote_ref_hash' => $this->quoteRefRedactor->hash($quoteRef),
            'error_code' => $errorCode,
            'error_category' => $reasonCode !== '' ? $reasonCode : $errorCode,
            'http_status' => $httpStatus,
            'acceptance_ref' => null,
            'idempotency_key' => $idempotencyKey,
            'timestamp' => now()->toIso8601String(),
        ], $extraMeta);

        if (Schema::hasTable('betting_activity_logs')) {
            BettingActivityLog::query()->create([
                'entity_type' => 'adapter_acceptance_attempt',
                'entity_id' => 0,
                'betting_event_id' => null,
                'betting_acceptance_id' => null,
                'betting_settlement_id' => null,
                'cms_user_id' => optional($actor)->id,
                'action' => 'acceptance.adapter_vps_api_rejected',
                'level' => 'warning',
                'message' => 'Adapter quote_ref acceptance rejected.',
                'meta' => $meta,
            ]);

            return $supportCode;
        }

        Log::warning('Adapter quote_ref acceptance rejected.', $meta);

        return $supportCode;
    }

    private function logRejectedAttempt(
        BettingQuoteSelection $selection,
        array $payload,
        ?string $idempotencyKey,
        string $reasonCode,
        string $message,
        $actor,
        ?string $requestId = null
    ): void {
        $selection->loadMissing('quoteVersion.event');
        $event = optional($selection->quoteVersion)->event;

        if (!$event) {
            return;
        }

        $this->activityLogService->logEvent(
            $event,
            'acceptance.api_rejected',
            'Acceptance request rejected through betting API.',
            [
                'reason_code' => $reasonCode,
                'message' => $message,
                'quote_version_id' => (int) $payload['quote_version_id'],
                'quote_selection_id' => (int) $payload['quote_selection_id'],
                'stake_amount' => (string) $payload['stake_amount'],
                'currency' => $payload['currency'],
                'idempotency_key' => $idempotencyKey,
                'request_source' => 'cms_betting_api',
                'actor_role' => request()->attributes->get('cms_api_actor_role') ?: optional($actor)->role,
                'actor_type' => request()->attributes->get('cms_api_actor_type', 'cms_user'),
                'auth_mode' => request()->attributes->get('cms_api_auth_mode', 'session'),
                'request_id' => $requestId ?: $this->resolveRequestId(request()),
            ],
            $actor,
            'warning'
        );
    }

    private function resolveRequestId(Request $request): string
    {
        $requestId = (string) $request->attributes->get('betting_api_request_id', '');

        if ($requestId !== '') {
            return $requestId;
        }

        $requestId = trim((string) $request->header('X-Request-Id', ''));
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('betting_api_request_id', $requestId);

        return $requestId;
    }

    private function recordRiskLimitBlock(
        Request $request,
        array $mapped,
        array $payload,
        mixed $actor,
        ?string $sourceTable = null,
        ?int $sourceId = null
    ): void {
        // Rejection is already recorded in betting_activity_logs; Ops Radar is outside this transfer.
}
}
