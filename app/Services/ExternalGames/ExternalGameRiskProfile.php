<?php
namespace App\Services\ExternalGames;
use App\SiteSettings;

/** Source CanonicalRiskLimitProfileService's external-only read contract. */
final class ExternalGameRiskProfile
{
    public function externalLimits(): array
    {
        $stored=json_decode((string)SiteSettings::where('key',config('risk_limits.profile_settings_key','canonical_risk_limits_v1'))->value('value'),true)?:[];
        if (data_get($stored,'products.external_games.approval.status')!=='operator_approved') return [];
        $v=(array)data_get($stored,'products.external_games.limits',[]);
        return array_filter(['enabled'=>true,'max_single_bet'=>$v['max_single_bet_try']??null,
            'rolling_24h_stake_limit'=>$v['rolling_24h_stake_try']??null,
            'rolling_24h_net_loss_limit'=>$v['rolling_24h_net_loss_try']??null],fn($v)=>$v!==null);
    }
}
