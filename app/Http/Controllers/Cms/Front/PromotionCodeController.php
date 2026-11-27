<?php
namespace App\Http\Controllers\Cms\Front;
use App\Http\Controllers\Controller;
use App\Models\{CmsUser,CmsPromotionCode,CmsCurrency,CmsUserTransaction};
use App\Services\Wallet\{CanonicalWalletService,CanonicalWalletContext,SpendableWalletService,SpendableWalletContext,AssetWalletService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
/** Existing CMS promo redemption implementation; same canonical wallet/ledger chain. */
class PromotionCodeController extends Controller {
 public function __construct(private CanonicalWalletService $wallets){}
    public function checkPromotionCode(Request $request){
        try {
            $user = auth("cms")->user();
            if(!$user instanceof CmsUser){
                return response()->json([
                    "message" => trans("cms_alerts.login_first_or_click"),
                    "status" => 401,
                ]);
            }

            $code = trim((string)$request->get("promoCode"));

            return DB::transaction(function () use ($code, $user) {
                $promoCode = CmsPromotionCode::query()
                    ->whereRaw("LOWER(promo_code) = ?", [Str::lower($code)])
                    ->lockForUpdate()
                    ->first();

                if( ! $promoCode instanceof CmsPromotionCode){
                    return response()->json([
                        "message" => trans("cms_alerts.promo_code_not_found"),
                        "status" => 400,
                    ]);
                }

                if($promoCode->status !== "active"){
                    return response()->json([
                        "message" => trans("cms_alerts.promo_code_not_found"),
                        "status" => 400,
                    ]);
                }

                if(!is_null($promoCode->start_date) && now()->lt(\Illuminate\Support\Carbon::parse($promoCode->start_date))){
                    return response()->json([
                        "message" => trans("cms_alerts.promo_code_has_not_started_yet"),
                        "status" => 400,
                    ]);
                }

                if(!is_null($promoCode->end_date) && now()->gt(\Illuminate\Support\Carbon::parse($promoCode->end_date))){
                    return response()->json([
                        "message" => trans("cms_alerts.promo_code_has_been_expired"),
                        "status" => 400,
                    ]);
                }

                if($promoCode->promo_count <= 0){
                    return response()->json([
                        "message" => trans("cms_alerts.not_enough_promo_count"),
                        "status" => 400,
                    ]);
                }

                if($promoCode->promo_left <= 0){
                    return response()->json([
                        "message" => trans("cms_alerts.promo_code_has_been_finished"),
                        "status" => 400,
                    ]);
                }

                $walletContext = $this->wallets->resolveForUser($user, $user->currency, true);
                if (!$walletContext instanceof CanonicalWalletContext) {
                    return response()->json([
                        "message" => trans("cms_alerts.general_error"),
                        "status" => 500,
                    ]);
                }

                $checkTransaction = CmsUserTransaction::query()
                    ->where("promotion_code_id", $promoCode->id)
                    ->whereIn("cms_user_id", array_values(array_unique([
                        (int) $user->id,
                        $walletContext->ownerWalletId(),
                    ])))
                    ->lockForUpdate()
                    ->first();

                if($checkTransaction instanceof CmsUserTransaction){
                    return response()->json([
                        "message" => trans("cms_alerts.promo_code_already_used"),
                        "status" => 400,
                    ]);
                }

                $assetWallets = app(AssetWalletService::class);
                $spendableWallets = app(SpendableWalletService::class);
                $certification = app(\App\Services\Certification\ProductionCertificationContext::class);
                $certificationUser = $certification->isActiveFor($user) ? $user : $walletContext->ownerUser();
                $isCertification = $certification->isActiveFor($certificationUser);
                $walletAccount = $isCertification
                    ? $certification->walletFor($certificationUser, true, $walletContext->currency())
                    : $assetWallets->ensurePrimaryBalanceWallet($walletContext->ownerUser(), $walletContext->currency(), true);
                if ($isCertification && (!$walletAccount || !$certification->isCertificationWallet($walletAccount))) {
                    return response()->json(['message' => trans('cms_alerts.general_error'), 'status' => 422]);
                }
                $spendableContext = $walletAccount
                    ? $spendableWallets->resolveForWallet($walletContext->contextUser(), $walletAccount, true, $walletContext->contextWalletId())
                    : null;

                if (!$spendableContext instanceof SpendableWalletContext) {
                    throw new \DomainException('Canonical promo wallet is unavailable.');
                }
                $transactionId = Uuid::uuid4()->toString();
                $userBalance = $spendableWallets->currentDecimalBalance($spendableContext);

                $promoAmount = $promoCode->promo_amount;
                $amount = \Brick\Math\BigRational::of((string) $promoAmount);
                $currency = $user->currency;

                if($promoCode->currency != $user->currency){

                    $targetCurrency = CmsCurrency::query()->where("code", $user->currency)->first();
                    $promoCurrency = CmsCurrency::query()->where("code", $promoCode->currency)->first();


                    $amount = $amount->multipliedBy((string) $promoCurrency->rate)->dividedBy((string) $targetCurrency->rate);
                }

                $amount = (string) $amount->toBigDecimal($spendableContext->precision(), \Brick\Math\RoundingMode::HALF_UP);
                if (!\Brick\Math\BigDecimal::of($amount)->isPositive()) {
                    throw new \DomainException('Promo amount must be positive.');
                }
                $afterBalance = (string) \Brick\Math\BigDecimal::of($userBalance)->plus($amount);
                $transactionData = [
                    "cms_user_id" => $walletContext->ownerWalletId(),
                    "transactionId" => $transactionId,
                    "action" => 3, //promotion
                    "status" => 1,
                    "amount" => $amount,
                    "currency" => $currency,
                    "before_balance" => $userBalance,
                    "after_balance" => $afterBalance,
                    "promotion_code_id" => $promoCode->id,
                    "event_type" => "promo_code_credit",
                    "source_wallet_account_id" => $spendableContext?->walletAccountId(),
                    "source_wallet_type" => $spendableContext?->walletType(),
                    "source_currency" => $walletContext->currency(),
                    "transaction_asset" => $walletContext->currency(),
                    "transaction_precision_scale" => $spendableContext?->precision() ?? 2,
                    "exact_precision_source" => $spendableContext instanceof SpendableWalletContext
                        ? "wallet_ledger:promo_code_credit_granted"
                        : null,
                    "exact_amount" => $amount,
                    "exact_before_balance" => $userBalance,
                    "exact_after_balance" => $afterBalance,
                ];
                if ($isCertification) {
                    $transactionData['execution_context_snapshot'] = array_merge($certification->auditPayload($certificationUser), [
                        'non_redeemable' => true, 'economic_boundary' => 'certification_only',
                    ]);
                }

                $createTransaction = CmsUserTransaction::query()->create($transactionData);

                if($createTransaction){
                    if ($spendableContext instanceof SpendableWalletContext) {
                        $spendableWallets->projectDecimalBalance($spendableContext, $afterBalance);

                        $spendableWallets->recordDecimalLedgerEntry(
                            $spendableContext,
                            'promo_code_credit_granted',
                            'credit',
                            $amount,
                            $userBalance,
                            $afterBalance,
                            'promo_code_credit:' . (int) $promoCode->id . ':user:' . $walletContext->ownerWalletId(),
                            $createTransaction,
                            array_merge($isCertification ? $transactionData['execution_context_snapshot'] : [], [
                                'audit_only' => true,
                                'source' => 'promo_code_redemption_v1',
                                'promo_code_id' => (int) $promoCode->id,
                                'promo_code' => (string) $promoCode->promo_code,
                                'target_user_id' => (int) $user->id,
                                'wallet_owner_user_id' => $walletContext->ownerWalletId(),
                                'cms_user_transaction_id' => (int) $createTransaction->id,
                                'transactionId' => (string) $transactionId,
                                'amount' => $amount,
                                'currency' => (string) $currency,
                            ])
                        );
                    }

                    $promoCode->forceFill([
                       "promo_left" => max(0, (int) $promoCode->promo_left - 1),
                    ])->save();

                    return response()->json([
                        "message" => trans("cms_alerts.promotion_code_success"),
                        "status" => 200,
                    ]);
                }

                return response()->json([
                    "message" => trans("cms_alerts.promotion_code_fail"),
                    "status" => 400,
                ]);
            }, 3);

            return response()->json([
                "message" => trans("cms_alerts.promotion_code_fail"),
                "status" => 400,
            ]);
        }catch (\Exception $exception){

            return response()->json([
                "message" => trans("cms_alerts.general_error"),
                "status" => 400,
            ]);
        }
    }

}
