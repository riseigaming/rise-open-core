<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Services\CmsStaff\CmsStaffPermissionService as Permissions;
use App\Services\ExternalGames\ExternalGameProviderConfig;
use App\Services\OpsRadar\OpsRadarCockpitReadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OpsRadarController extends Controller
{
    public function index(Request $request, OpsRadarCockpitReadService $radar, Permissions $permissions)
    {
        $actor = auth('cms')->user();
        $finance = $permissions->has($actor, Permissions::FINANCE_MANUAL_OVERRIDE);
        $providers = $permissions->has($actor, Permissions::CATALOG_PROVIDERS);
        abort_unless($finance || $providers, 403);

        $aliases = $providers ? array_keys(ExternalGameProviderConfig::providers()) : [];
        if ($finance) {
            $aliases[] = 'nova';
        }
        $filters = $request->validate([
            'provider' => ['nullable', 'string', Rule::in($aliases)],
            'state' => ['nullable', 'string', Rule::in(OpsRadarCockpitReadService::STATES)],
            'reference' => ['nullable', 'string', 'regex:/\A(?:' . implode('|', OpsRadarCockpitReadService::SOURCES) . '):[1-9][0-9]{0,17}\z/'],
        ]);
        $cockpit = $radar->cockpit($filters, $providers, $finance, $permissions->isAdmin($actor));
        $response = $request->expectsJson() ? response()->json($cockpit) : response()->view('cms.admin.ops-radar.index', [
            'cockpit' => $cockpit, 'filters' => $filters, 'aliases' => $aliases, 'states' => OpsRadarCockpitReadService::STATES,
        ]);
        return $response->header('Cache-Control', 'private, no-store');
    }
}
