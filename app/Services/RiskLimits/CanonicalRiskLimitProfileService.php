<?php
namespace App\Services\RiskLimits;
use App\SiteSettings;
/** Frozen canonical profile read seam; no second risk/finance store. */
final class CanonicalRiskLimitProfileService
{
    public function sportsbookLimits():array {
        $p=json_decode((string)SiteSettings::where('key',config('risk_limits.profile_settings_key','canonical_risk_limits_v1'))->value('value'),true)?:[];
        $fallback=app(\App\Betting\Acceptance\StakeLimitPolicy::class)->configuredConfigLimits();
        if(data_get($p,'products.sportsbook.approval.status')!=='operator_approved')return $fallback;
        foreach((array)data_get($p,'products.sportsbook.currencies',[])as $asset=>$row)
            if(is_numeric($row['max_single_stake']??null)&&(float)$row['max_single_stake']>0)$fallback[strtoupper($asset)]=round((float)$row['max_single_stake'],2);
        return $fallback;
    }

 public function chessbetLimitsForCurrency(string $currency):?array {
  $stored=json_decode((string)SiteSettings::where('key',config('risk_limits.profile_settings_key','canonical_risk_limits_v1'))->value('value'),true)?:[];
  if(data_get($stored,'products.chessbet.approval.status')!=='operator_approved')return null;
  $row=data_get($stored,'products.chessbet.currencies.'.strtoupper($currency));if(!is_array($row))return null;
  return ['min'=>(string)($row['minimum_stake']??0),'max'=>(string)($row['maximum_stake']??0),'market_exposure'=>(string)($row['market_exposure']??0)];
 }
}
