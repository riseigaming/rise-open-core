<?php
namespace App\Http\Controllers\Cms\Admin;
use App\Http\Controllers\Controller;
use App\Models\{CmsGame,Provider,ExternalGameSession};
use App\Services\ExternalGames\{ExternalGamesControlSettingsService,ExternalGameCatalogImportService,ExternalGameProviderRolloutService,ExternalGameCallbackFinanceReconciliationService,ExternalGameCallbackHealthService,ExternalGameProviderConfig,ExternalGamesHealthSummaryService};
use App\Services\Games\CatalogAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Source ExternalGamesControlController actions adapted to the current Rise admin. */
final class ExternalGamesControlController extends Controller
{
    public function edit(Request $r,ExternalGamesControlSettingsService $controls,ExternalGameProviderRolloutService $rollout,ExternalGameCallbackFinanceReconciliationService $finance,ExternalGameCallbackHealthService $health)
    {
        $providers = ExternalGameProviderConfig::providers();
        $v = $r->validate(['user_id'=>'nullable|integer|exists:cms_users,id',
            'provider_alias'=>['nullable', 'string', Rule::in(array_keys($providers))]]);
        $alias = $v['provider_alias'] ?? ExternalGameProviderConfig::PRIMARY;
        $entry = $providers[$alias];
        $configured = ExternalGameProviderConfig::enabled($alias)
            && ExternalGameProviderConfig::distinctWalletCredentials($alias)
            && filled($entry['api_base_url'] ?? null) && filled($entry['agent_token'] ?? null)
            && filled($entry['agent_code'] ?? null) && filled($entry['agent_secret'] ?? null);
        $selectedUser=empty($v['user_id'])?null:\App\Models\CmsUser::findOrFail($v['user_id']);
        return view('cms.admin.providers.external',[
            'selectedUser'=>$selectedUser,'eligibility'=>$selectedUser?app(\App\Services\ExternalGames\ExternalGameRealDepositEligibilityService::class)->resolve($selectedUser):null,
            'controls'=>$controls->settings(),'provider'=>Provider::where('slug',$alias)->first(),
            'providerAliases'=>array_keys($providers),'selectedAlias'=>$alias,
            'games'=>CmsGame::with('provider')->whereHas('provider',fn($q)=>$q->where('slug',$alias))->get(),
            'sessions'=>ExternalGameSession::where('provider_alias',$alias)->latest('id')->limit(30)->get(),
            'rollout'=>$rollout,'health'=>$health->forProvider($alias)->summary(),'reconciliation'=>$finance->forProvider($alias)->report(),
            'providerSummary'=>app(ExternalGamesHealthSummaryService::class)->summary($alias),
            'risk'=>app(\App\Services\ExternalGames\ExternalGameRiskLimitService::class)->resolvedLimits(),
            'configured'=>$configured,
        ]);
    }
    public function risk(Request $r)
    {
        $v=$r->validate(['cms_user_id'=>'nullable|integer|exists:cms_users,id','kind'=>'required|in:global_risk,user_risk,eligibility_grant,eligibility_revoke,user_risk_revoke',
            'max_single_bet'=>'nullable|numeric|min:0|max:999999999','rolling_24h_stake_limit'=>'nullable|numeric|min:0|max:999999999','rolling_24h_net_loss_limit'=>'nullable|numeric|min:0|max:999999999',
            'expires_at'=>'nullable|date|after:now','reason'=>'required|string|min:5|max:255']);
        $actor=auth('cms')->user();$user=empty($v['cms_user_id'])?null:\App\Models\CmsUser::findOrFail($v['cms_user_id']);
        if($v['kind']==='global_risk') {
            abort_unless($r->boolean('confirm_material_change'),422,'Explicit material change confirmation required.');
            DB::transaction(function()use($v,$actor){
                $row=\App\SiteSettings::where('key',config('external_games.controls_settings_key'))->lockForUpdate()->firstOrFail();
                $settings=json_decode($row->value,true)?:[];
                foreach(['max_single_bet','rolling_24h_stake_limit','rolling_24h_net_loss_limit']as $k){abort_unless(isset($v[$k]),422);$settings['risk_limits'][$k]=$v[$k];}
                $settings['risk_limits']['enabled']=true;
                abort_unless((float)$v['max_single_bet']>0 && (float)$v['rolling_24h_stake_limit']>=(float)$v['max_single_bet'] && (float)$v['rolling_24h_net_loss_limit']>0,422,'Positive limits and daily stake hierarchy required.');
                $key=config('risk_limits.profile_settings_key','canonical_risk_limits_v1');
                $stored=\App\SiteSettings::where('key',$key)->lockForUpdate()->first();$profile=json_decode((string)$stored?->value,true)?:[];
                $profile['products']['external_games']=['limits'=>['max_single_bet_try'=>$v['max_single_bet'],'rolling_24h_stake_try'=>$v['rolling_24h_stake_limit'],'rolling_24h_net_loss_try'=>$v['rolling_24h_net_loss_limit']],
                    'approval'=>['status'=>'operator_approved','approved_at'=>now()->toDateTimeString(),'approved_by'=>$actor->id,'reason'=>$v['reason'],'version'=>'provisional_launch_v1']];
                $profile['updated_at']=now()->toDateTimeString();$profile['updated_by']=$actor->id;$profile['reason']=$v['reason'];$profile['version']='provisional_launch_v1';
                \App\SiteSettings::updateOrCreate(['key'=>$key],['value'=>json_encode($profile,JSON_THROW_ON_ERROR)]);
                $row->update(['value'=>json_encode($settings)]);
                CatalogAudit::record('external_games.risk.changed',$actor,['reason'=>$v['reason'],'scope'=>'external_games']);
            });
        } else {
            abort_unless($user,422);
            $risk=app(\App\Services\ExternalGames\ExternalGameUserRiskLimitOverrideService::class);
            $eligibility=app(\App\Services\ExternalGames\ExternalGameRealMoneyEligibilityOverrideService::class);
            match($v['kind']) {
                'user_risk'=>$risk->upsert($user,array_intersect_key($v,array_flip(['max_single_bet','rolling_24h_stake_limit','rolling_24h_net_loss_limit'])),$v['reason'],$actor,$v['expires_at']??null),
                'user_risk_revoke'=>$risk->revoke($user,$v['reason'],$actor),
                'eligibility_grant'=>$eligibility->upsert($user,$v['reason'],$actor,$v['expires_at']??null),
                'eligibility_revoke'=>$eligibility->revoke($user,$v['reason'],$actor),
            };
        }
        return back()->with('catalog_status','External risk / eligibility decision audited.');
    }

    public function update(Request $r,ExternalGamesControlSettingsService $controls)
    {
        $v=$r->validate(['enabled'=>'required|boolean','emergency_stop'=>'required|boolean','demo_enabled'=>'required|boolean',
            'pilot_policy'=>'required|in:default_open,manual_approval_for_new_users,pilot_cohort_only','reason'=>'required|string|min:5|max:255']);
        DB::transaction(function()use($controls,$v){$controls->persist($v,auth('cms')->user());CatalogAudit::record('external_games.controls.changed',auth('cms')->user(),['reason'=>$v['reason']]);});
        return back()->with('catalog_status','External Games controls saved.');
    }
    public function syncCatalog(Request $request, ExternalGameCatalogImportService $catalog)
    {
        $input = $request->validate(['provider_alias'=>['nullable', 'string', Rule::in(array_keys(ExternalGameProviderConfig::providers()))]]);
        $alias = $input['provider_alias'] ?? ExternalGameProviderConfig::PRIMARY;
        abort_unless(ExternalGameProviderConfig::enabled($alias), 422);
        try{$summary=$catalog->sync($alias, actorId:auth('cms')->id());CatalogAudit::record('external_games.catalog.imported',auth('cms')->user(),$summary);}
        catch(\Throwable $e){$catalog->recordFailure('Provider catalog request failed.',auth('cms')->id(),$alias);return back()->withErrors(['catalog'=>'Provider catalog request failed.']);}
        return back()->with('catalog_status','Catalog imported: '.$summary['seen_count']);
    }
    public function updateRollout(Request $r,ExternalGameProviderRolloutService $rollout)
    {
        $v=$r->validate(['state'=>'required|in:hidden,preview,canary,active,maintenance,disabled','game_id'=>'nullable|integer|exists:cms_games,id','provider_key'=>'nullable|string|max:191','reason'=>'required|string|min:5|max:255']);
        DB::transaction(function()use($v,$rollout){
            if(!empty($v['game_id'])){$game=CmsGame::with('provider')->findOrFail($v['game_id']);abort_unless($rollout->isExternalGame($game),422);$rollout->setGameState($game,$v['state'],auth('cms')->id());$subject=$game;}
            else {abort_unless(filled($v['provider_key']??''),422);$rollout->setProviderState($v['provider_key'],$v['state'],auth('cms')->id());$subject=auth('cms')->user();}
            CatalogAudit::record('external_games.rollout.changed',$subject,['state'=>$v['state'],'reason'=>$v['reason']]);
        });
        return back()->with('catalog_status','Rollout saved.');
    }
}
