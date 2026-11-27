<?php

namespace App\Models;

use App\Enum\TransactionActionEnum;
use App\Services\VirtualShowcase\VirtualShowcaseMoneyFlowGuard;
use App\Support\Money\CurrencyPrecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CmsUserTransaction extends Model
{
    use HasFactory;

    private const ASSET_CODE_PRIORITY = ['transaction_asset', 'source_currency', 'currency', 'requested_payout_currency'];

    public const SUPPORT_PENDING_PAYMENT_CANCELLATION_REASON = 'support_user_cancelled_pending_payment';
    public const SUPPORT_PENDING_PAYMENT_CANCELLATION_ADMIN_LABEL = 'Canlı destek üzerinden kullanıcı onayı ile iptal edildi';

    protected $table  = "cms_user_transactions";
    protected $guarded = ["id"];
    protected $fillable = [
        "cms_user_id",
        "cms_partner_id",
        "promotion_code_id",
        "transactionId",
        "ref_transaction_id",
        "round_id",
        "payment_api_url",
        "bonus_id",
        "free_round_id",
        "event_type",
        "provider_transaction_id",
        "game_provider_id",
        "game_id",
        "rejected_reason",
        "action",
        "status",
        "amount",
        "currency",
        "transaction_asset",
        "crypto_deposit_context",
        "transaction_precision_scale",
        "exact_precision_source",
        "exact_precision_backfilled_at",
        "exact_amount",
        "source_wallet_account_id",
        "source_wallet_type",
        "source_currency",
        "requested_payout_currency",
        "payout_route_type",
        "payout_requires_conversion",
        "payout_conversion_policy",
        "payout_route_reason",
        "payout_context_snapshot",
        "execution_wallet_account_id",
        "execution_currency",
        "execution_context_snapshot",
        "settled_wallet_account_id",
        "settled_currency",
        "before_balance",
        "after_balance",
        "exact_before_balance",
        "exact_after_balance",
        "tax_percentage",
        "tax_amount",
        "taxed_amount",
        "acceptor",
        "created_by_admin",
    ];

    protected $casts = [
        'crypto_deposit_context' => 'array',
        'payout_requires_conversion' => 'boolean',
        'payout_context_snapshot' => 'array',
        'execution_context_snapshot' => 'array',
        'transaction_precision_scale' => 'integer',
        'exact_precision_backfilled_at' => 'datetime',
        'exact_amount' => 'decimal:8',
        'exact_before_balance' => 'decimal:8',
        'exact_after_balance' => 'decimal:8',
    ];

    protected $appends = ["modified_date"];

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            app(VirtualShowcaseMoneyFlowGuard::class)->assertUserIdAllowed(
                (int) $transaction->cms_user_id,
                'cms_user_transaction_create'
            );

            $transaction->synchronizeExactPrecisionSnapshot();
        });

        static::updating(function (self $transaction) {
            if ($transaction->isDirty('crypto_deposit_context')) {
                throw new \LogicException('Crypto deposit context is immutable after creation.');
            }
            if ($transaction->crypto_deposit_context !== null
                && ($transaction->isDirty('transaction_asset') || $transaction->isDirty('transaction_precision_scale'))) {
                throw new \LogicException('Pinned crypto deposit asset and precision are immutable.');
            }
            $transaction->assertWithdrawalIdentityUnchanged();
            $transaction->normalizeExistingExactPrecisionSnapshot();
        });
    }

    private function assertWithdrawalIdentityUnchanged(): void
    {
        // Original values prevent changing the qualification in the same update.
        // The reference and source wallet bind payout debit/refund ledger evidence.
        if (!$this->exists
            || (int) $this->getRawOriginal('action') !== TransactionActionEnum::Withdraw
            || (int) $this->getRawOriginal('source_wallet_account_id') <= 0
            || trim((string) $this->getRawOriginal('transactionId')) === '') {
            return;
        }

        foreach (['transactionId', 'action', 'source_wallet_account_id', 'cms_user_id',
            'source_wallet_type', 'source_currency', 'transaction_asset'] as $attribute) {
            // Historical missing snapshots may still be populated. Once pinned,
            // owner/source identity cannot be rewritten; settlement fields can.
            if (trim((string) $this->getRawOriginal($attribute)) !== '' && $this->isDirty($attribute)) {
                throw new \LogicException('Pinned withdrawal identity is immutable: '.$attribute.'.');
            }
        }
    }

    public function user(){
        return $this->belongsTo(CmsUser::class, "cms_user_id");
    }

    public function partner(){
        return $this->belongsTo(CmsPartner::class, "cms_partner_id");
    }

    public function promotionCode(){
        return $this->belongsTo(CmsPromotionCode::class, "promotion_code_id");
    }

    public function game(){
        return $this->belongsTo(CmsGame::class, "game_id");
    }

    public function gameProvider(){
        return $this->belongsTo(Provider::class, "game_provider_id");
    }

    public function bonused(){
        return $this->hasOne(self::class, "ref_transaction_id", "transactionId");
    }
    public function bonus(){
        return $this->belongsTo(CmsBonus::class, "bonus_id");
    }

    public function freeRound(){
        return $this->belongsTo(FreeRound::class, "free_round_id");
    }

    public function supportCredit()
    {
        return $this->hasOne(CmsSupportCredit::class, 'grant_transaction_id', 'transactionId');
    }

    public function scopeExcludeCertification(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('source_wallet_type')
                ->orWhere('source_wallet_type', '!=', (string) config('production_certification.wallet_type', 'certification_balance'));
        });
    }

    public function scopeOnlyCertification(Builder $query): Builder
    {
        return $query->where('source_wallet_type', (string) config('production_certification.wallet_type', 'certification_balance'));
    }

    public function adminCancellationSourceLabel(): ?string
    {
        return (string) $this->rejected_reason === self::SUPPORT_PENDING_PAYMENT_CANCELLATION_REASON
            ? self::SUPPORT_PENDING_PAYMENT_CANCELLATION_ADMIN_LABEL
            : null;
    }

    public function getModifiedDateAttribute(){
        return date("d/m/Y H:i:s", strtotime($this->created_at));
    }

    public function transactionAssetCode(): string
    {
        foreach (self::ASSET_CODE_PRIORITY as $column) {
            $code = strtoupper(trim((string) $this->{$column}));
            if ($code !== '') {
                return $code;
            }
        }

        return 'TRY';
    }

    public static function transactionAssetColumns(): array
    {
        return self::ASSET_CODE_PRIORITY;
    }

    public static function transactionAssetSqlExpression(?array $availableColumns = null): string
    {
        $columns = array_values(array_filter(
            self::ASSET_CODE_PRIORITY,
            static fn (string $column) => $availableColumns === null || in_array($column, $availableColumns, true)
        ));
        $columns = array_map(static fn (string $column) => "NULLIF(TRIM({$column}), '')", $columns);

        if ($columns === []) {
            return "'TRY'";
        }

        return "UPPER(COALESCE(" . implode(', ', $columns) . ", 'TRY'))";
    }

    public function transactionPrecisionScale(): int
    {
        return (int) ($this->transaction_precision_scale
            ?: CurrencyPrecision::scaleFor($this->transactionAssetCode(), $this->source_wallet_type));
    }

    public function usesExactPrecision(): bool
    {
        return $this->transactionPrecisionScale() > CurrencyPrecision::fiatScale();
    }

    public function displayAmountValue(): string
    {
        return $this->normalizedExactValue('exact_amount', 'amount');
    }

    public function displayBeforeBalanceValue(): string
    {
        return $this->normalizedExactValue('exact_before_balance', 'before_balance');
    }

    public function displayAfterBalanceValue(): string
    {
        return $this->normalizedExactValue('exact_after_balance', 'after_balance');
    }

    public function exactAmountValue(): ?string
    {
        return $this->normalizeValue($this->exact_amount);
    }

    public function exactBeforeBalanceValue(): ?string
    {
        return $this->normalizeValue($this->exact_before_balance);
    }

    public function exactAfterBalanceValue(): ?string
    {
        return $this->normalizeValue($this->exact_after_balance);
    }

    public function hasExactPrecisionSnapshot(): bool
    {
        return $this->exactAmountValue() !== null
            && $this->exactBeforeBalanceValue() !== null
            && $this->exactAfterBalanceValue() !== null;
    }

    public function legacyAmountValue(): string
    {
        return CurrencyPrecision::formatLegacy($this->amount, $this->usesExactPrecision());
    }

    public function legacyBeforeBalanceValue(): string
    {
        return CurrencyPrecision::formatLegacy($this->before_balance, $this->usesExactPrecision());
    }

    public function legacyAfterBalanceValue(): string
    {
        return CurrencyPrecision::formatLegacy($this->after_balance, $this->usesExactPrecision());
    }

    public function displayAmountCurrencyCode(): string
    {
        if ($this->exactAmountValue() !== null) {
            return $this->transactionAssetCode();
        }

        return strtoupper((string) ($this->requested_payout_currency ?: $this->currency ?: $this->transactionAssetCode()));
    }

    public function displayBalanceCurrencyCode(): string
    {
        if ($this->exactBeforeBalanceValue() !== null || $this->exactAfterBalanceValue() !== null) {
            return $this->transactionAssetCode();
        }

        return strtoupper((string) ($this->source_currency ?: $this->currency ?: $this->transactionAssetCode()));
    }

    public function cryptoRailPresentation(): ?array
    {
        $asset = $this->transactionAssetCode();
        $isCrypto = (string) $this->source_wallet_type === 'crypto_asset_wallet'
            || (string) optional($this->partner)->partner_type === 'crypto';
        if (!$isCrypto || !preg_match('/^[A-Z0-9]{2,16}$/', $asset)) {
            return null;
        }

        $network = collect([
            data_get($this->execution_context_snapshot, 'rail.network'),
            data_get($this->execution_context_snapshot, 'network'),
            data_get($this->execution_context_snapshot, 'crypto.network'),
            data_get($this->payout_context_snapshot, 'rail.network'),
            data_get($this->payout_context_snapshot, 'network'),
        ])->first(fn ($value) => is_string($value) && preg_match('/^[A-Z0-9]{2,16}$/', strtoupper(trim($value))));
        $network = is_string($network) ? strtoupper(trim($network)) : null;

        // Native historical deposits have a deterministic same-name network.
        if ($network === null && in_array($asset, ['BTC', 'LTC'], true)) {
            $network = $asset;
        }

        $name = match ($asset) {
            'BTC' => 'Bitcoin',
            'LTC' => 'Litecoin',
            'USDT' => 'Tether',
            default => 'Crypto',
        };

        return [
            'asset' => $asset,
            'network' => $network,
            'name' => $name,
            'rail_label' => $asset === 'USDT'
                ? ($network ? $asset.' · '.$network : $asset.' · Ağ bilinmiyor')
                : ($network && $network !== $asset ? $asset.' · '.$network : $asset),
        ];
    }

    private function synchronizeExactPrecisionSnapshot(bool $fillMissingOnly = false): void
    {
        $asset = $this->transactionAssetCode();
        $scale = $this->crypto_deposit_context !== null
            ? app(\App\Services\Finance\CryptoSettlementRailPolicy::class)->resolve($asset,
                $this->crypto_deposit_context['network'] ?? '', $this->crypto_deposit_context['precision'] ?? null)->precision
            : CurrencyPrecision::scaleFor($asset, $this->source_wallet_type);

        if (!$fillMissingOnly || empty($this->transaction_asset)) {
            $this->transaction_asset = $asset;
        }

        if (!$fillMissingOnly || empty($this->transaction_precision_scale)) {
            $this->transaction_precision_scale = $scale;
        }

        $this->hydrateExactSnapshotAttribute('exact_amount', 'amount', $scale, $fillMissingOnly);
        $this->hydrateExactSnapshotAttribute('exact_before_balance', 'before_balance', $scale, $fillMissingOnly);
        $this->hydrateExactSnapshotAttribute('exact_after_balance', 'after_balance', $scale, $fillMissingOnly);

        if (($this->exactAmountValue() !== null || $this->exactBeforeBalanceValue() !== null || $this->exactAfterBalanceValue() !== null)
            && (!$fillMissingOnly || empty($this->exact_precision_source))
        ) {
            $this->exact_precision_source = $this->exact_precision_source ?: 'transaction_snapshot';
        }
    }

    private function normalizeExistingExactPrecisionSnapshot(): void
    {
        $asset = $this->transactionAssetCode();
        $scale = $this->crypto_deposit_context !== null
            ? $this->transaction_precision_scale
            : CurrencyPrecision::scaleFor($asset, $this->source_wallet_type);
        $hasExactInput = $this->getAttribute('exact_amount') !== null
            || $this->getAttribute('exact_before_balance') !== null
            || $this->getAttribute('exact_after_balance') !== null
            || $this->getAttribute('transaction_asset') !== null
            || $this->getAttribute('transaction_precision_scale') !== null;

        if (!$hasExactInput && !$this->hasExactPrecisionSnapshot()) {
            return;
        }

        $this->transaction_asset = $this->transaction_asset ?: $asset;
        $this->transaction_precision_scale = $this->transaction_precision_scale ?: $scale;

        foreach (['exact_amount', 'exact_before_balance', 'exact_after_balance'] as $attribute) {
            $value = $this->getAttribute($attribute);
            if ($value !== null && $value !== '') {
                $this->setAttribute($attribute, CurrencyPrecision::normalize($value, $scale));
            }
        }

        if (($this->exactAmountValue() !== null || $this->exactBeforeBalanceValue() !== null || $this->exactAfterBalanceValue() !== null)
            && empty($this->exact_precision_source)
        ) {
            $this->exact_precision_source = 'transaction_snapshot';
        }
    }

    private function hydrateExactSnapshotAttribute(string $exactAttribute, string $fallbackAttribute, int $scale, bool $fillMissingOnly): void
    {
        $exactValue = $this->getAttribute($exactAttribute);
        if ($exactValue !== null && $exactValue !== '') {
            $this->setAttribute($exactAttribute, CurrencyPrecision::normalize($exactValue, $scale));

            return;
        }

        if ($fillMissingOnly && ($exactValue !== null && $exactValue !== '')) {
            return;
        }

        $fallbackValue = $this->getAttribute($fallbackAttribute);
        if ($fallbackValue === null || $fallbackValue === '') {
            return;
        }

        $this->setAttribute($exactAttribute, CurrencyPrecision::normalize($fallbackValue, $scale));
    }

    private function normalizeValue(int|float|string|null $value): ?string
    {
        return CurrencyPrecision::normalize($value, $this->transactionPrecisionScale());
    }

    private function normalizedExactValue(string $exactAttribute, string $fallbackAttribute): string
    {
        return $this->normalizeValue($this->getAttribute($exactAttribute))
            ?? $this->normalizeValue($this->getAttribute($fallbackAttribute))
            ?? CurrencyPrecision::format(0, $this->transactionPrecisionScale());
    }
}
