<?php

namespace App\Services\Restrictions;

use App\Http\Middleware\CmsLanguageMiddleware;
use App\Services\Geo\CmsCountryCodeResolver;
use App\Services\Geo\GeoIpResolution;
use App\Services\Geo\RequestGeoIpResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProductCountryRestrictionGuard
{
    private const RUNTIME_ATTRIBUTE = 'central_country_restriction_runtime';

    public function __construct(
        private readonly RequestGeoIpResolver $geoResolver,
        private readonly CmsCountryCodeResolver $countryCodes,
        private readonly CentralRestrictionPolicyService $restrictions
    ) {
    }

    public function decide(Request $request, string $productKey, array $context = []): RestrictionDecision
    {
        $runtime = $this->runtime($request);
        $geo = $runtime['geo'];
        $countryId = $runtime['country_id'];
        $restrictionContext = array_merge($context, $geo->restrictionContext());
        $normalizedProduct = strtolower(trim($productKey));

        try {
            $decision = $this->restrictions->decideCountryRestriction(
                $countryId,
                $productKey,
                $restrictionContext
            );
        } catch (Throwable) {
            $decision = new RestrictionDecision(
                true,
                $normalizedProduct,
                $countryId,
                'restriction_service_unavailable',
                CentralRestrictionPolicyService::SETTINGS_KEY,
                CentralRestrictionPolicyService::SETTINGS_VERSION,
                false,
                $geo->vpnProxyDetected,
                false,
                $restrictionContext
            );
        }

        return $this->recordDecision($request, $decision);
    }

    private function recordDecision(Request $request, RestrictionDecision $decision): RestrictionDecision
    {
        $decisions = (array) $request->attributes->get('country_restriction_decisions', []);
        $decisions[$decision->productKey] = $decision;
        $request->attributes->set('country_restriction_decisions', $decisions);
        $request->attributes->set('product_restriction_decision', $decision);
        $request->attributes->set('product_restriction_event_fields', $decision->toEventFields());

        return $decision;
    }

    public function blockedResponse(Request $request, RestrictionDecision $decision): Response
    {
        if (!$request->attributes->get('cms_locale_resolved', false)) {
            app(CmsLanguageMiddleware::class)->resolveLocale(
                $request,
                $request->attributes->get(self::RUNTIME_ATTRIBUTE)['geo'] ?? null
            );
        }

        $copyKey = $decision->productKey === CentralRestrictionPolicyService::PRODUCT_SITE ? 'site' : 'product';
        $message = trans("geo_restriction.$copyKey.message");

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 451,
                'success' => false,
                'data' => null,
                'message' => $message,
                'reason_code' => $decision->reasonCode,
                'product' => $decision->productKey,
                'error' => [
                    'code' => 'PRODUCT_GEO_RESTRICTED',
                    'message' => $message,
                    'details' => [
                        'reason_code' => $decision->reasonCode,
                        'product' => $decision->productKey,
                    ],
                ],
            ], 451)->header('Cache-Control', 'no-store, private')
                ->header('Content-Language', app()->getLocale());
        }

        return response()
            ->view('geo-restricted', ['decision' => $decision], 451)
            ->header('Cache-Control', 'no-store, private')
            ->header('Content-Language', app()->getLocale());
    }

    private function runtime(Request $request): array
    {
        $cached = $request->attributes->get(self::RUNTIME_ATTRIBUTE);
        if (is_array($cached) && ($cached['geo'] ?? null) instanceof GeoIpResolution) {
            return $cached;
        }

        try {
            $geo = $this->geoResolver->resolve($request);
        } catch (Throwable) {
            $geo = GeoIpResolution::unavailable($request->ip(), 'geo_resolver_exception');
        }

        try {
            $countryId = $this->countryCodes->resolveCountryId($geo->observedCountryCode);
        } catch (Throwable) {
            $countryId = null;
        }

        $runtime = [
            'geo' => $geo,
            'country_id' => $countryId,
        ];
        $request->attributes->set(self::RUNTIME_ATTRIBUTE, $runtime);

        return $runtime;
    }
}
