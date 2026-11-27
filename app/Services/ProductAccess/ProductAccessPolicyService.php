<?php
namespace App\Services\ProductAccess;

use App\Models\{CmsUser,CmsUserProductAccessOverride,Provider};
use App\Services\Games\CatalogAudit;
use Illuminate\Support\Facades\DB;

/** Existing product/provider override store shared by catalog and authorized local launch/acceptance paths. */
class ProductAccessPolicyService
{
    public const PRODUCT_SPORTSBOOK='sportsbook';
    public const PRODUCTS = ['internal_games','external_games','sportsbook'];

    /** Source External Games defaults to operator approval; guest fun mode bypasses only that default. */
    public function assertExternalAllowed(?CmsUser $user, Provider $provider, bool $funMode=false): void
    {
        if (!$this->catalogAllowed($user,$provider)) throw new \App\Services\ExternalGames\ExternalGameLaunchBlockedException('product_denied','Product access denied.');
        if ($funMode) return;
        if (!$user || !in_array($user->status,['normal','accepted'],true)) throw new \App\Services\ExternalGames\ExternalGameLaunchBlockedException('account_unapproved','Account approval required.');
        // Source launch checks global product approval before its provider scope.
        // An allow on a different provider can never open this product globally.
        $allow=CmsUserProductAccessOverride::where('cms_user_id',$user->id)->where('product_key','external_games')
            ->where('provider_alias','')->where('access_mode','allow')->exists();
        if (!$allow) throw new \App\Services\ExternalGames\ExternalGameLaunchBlockedException('operator_approval_required','External Games requires explicit operator approval.');
    }

    public function assertAllowed(?CmsUser $user,string $product,?string $provider=null,array $context=[]): void
    {
        if ($product!=='sportsbook') throw new \InvalidArgumentException('Use the product-specific launch contract.');
        if (!$user || $user->status==='rejected' || CmsUserProductAccessOverride::where('cms_user_id',$user->id)
            ->where('product_key',$product)->whereIn('provider_alias',['',$provider??''])->where('access_mode','deny')->exists())
            throw new \App\Betting\Exceptions\AdapterVpsAcceptanceException('PRODUCT_ACCESS_DENIED','Sportsbook product access denied.',403);
    }

    public function catalogAllowed(?CmsUser $user, Provider $provider): bool
    {
        if (!$user) return true;
        if ($user->status === 'rejected') return false;
        // An allow never overrides an explicit product/provider deny or opens a launch path.
        return !CmsUserProductAccessOverride::where('cms_user_id',$user->id)->where('product_key',$provider->productKey())
            ->whereIn('provider_alias',['',$provider->slug])->where('access_mode','deny')->exists();
    }

    /** Read this user's denies once per catalog evaluation; never cache across evaluations. */
    public function catalogFilter(?CmsUser $user): \Closure
    {
        if (!$user) return static fn (Provider $provider): bool => true;
        if ($user->status === 'rejected') return static fn (Provider $provider): bool => false;
        // Keep string matching in MySQL: PHP array keys would change the existing
        // collation semantics for case variants of product keys/provider aliases.
        // BINARY kind comparison mirrors Provider::productKey()'s strict equality.
        $denied = Provider::join('cms_user_product_access_overrides as overrides', function ($join) {
            $join->on(function ($aliases) {
                $aliases->on('overrides.provider_alias','=','providers.slug')->orWhere('overrides.provider_alias','');
            })->whereRaw("overrides.product_key = CASE WHEN BINARY providers.kind = ? THEN ? ELSE ? END",
                ['internal','internal_games','external_games']);
        })->where('overrides.cms_user_id',$user->id)->where('overrides.access_mode','deny')
            ->pluck('providers.id')->flip()->all();
        return static fn (Provider $provider): bool => !isset($denied[$provider->id]);
    }

    public function upsertOverride(CmsUser $user, string $product, string $provider, string $mode, string $reason): void
    {
        abort_unless(in_array($product,self::PRODUCTS,true) && in_array($mode,['default','allow','deny'],true),422);
        if ($provider !== '') abort_unless(Provider::where('slug',$provider)->where('kind',$product === 'internal_games' ? 'internal' : 'external')->exists(),422);
        DB::transaction(function () use ($user,$product,$provider,$mode,$reason) {
            CmsUser::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $query=CmsUserProductAccessOverride::where('cms_user_id',$user->id)->where('product_key',$product)->where('provider_alias',$provider);
            if ($mode==='default') $query->delete();
            else CmsUserProductAccessOverride::updateOrCreate(['cms_user_id'=>$user->id,'product_key'=>$product,'provider_alias'=>$provider],['access_mode'=>$mode,'reason'=>$reason,'updated_by'=>auth('cms')->id()]);
            CatalogAudit::record('catalog.product_access.changed',$user,['product'=>$product,'provider'=>$provider,'mode'=>$mode]);
        });
    }
}
