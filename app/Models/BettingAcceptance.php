<?php

namespace App\Models;

use App\Services\VirtualShowcase\VirtualShowcaseMoneyFlowGuard;
use App\Services\Community\AcceptedCouponRoomDeliveryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BettingAcceptance extends Model
{
    use HasFactory;

    protected $fillable = [
        'acceptance_ref',
        'acceptance_contract_version',
        'acceptance_source',
        'idempotency_key',
        'request_fingerprint',
        'quote_ref',
        'quote_version_ref',
        'adapter_verification_id',
        'adapter_verification_digest',
        'adapter_payload_digest',
        'source_provider_key',
        'connector_key',
        'betting_quote_version_id',
        'betting_quote_selection_id',
        'betting_event_id',
        'betting_market_id',
        'betting_outcome_id',
        'cms_user_id',
        'root_user_id',
        'reviewed_by_cms_user_id',
        'currency',
        'stake_amount',
        'accepted_price',
        'status',
        'decision',
        'decision_source',
        'requested_at',
        'accepted_at',
        'reviewed_at',
        'last_transition_at',
        'rejected_at',
        'rejected_reason',
        'decision_reason',
        'context',
        'accepted_snapshot_json',
        'accepted_snapshot_digest',
        'override_meta',
    ];

    protected $casts = [
        'stake_amount' => 'decimal:6',
        'accepted_price' => 'decimal:6',
        'requested_at' => 'datetime',
        'accepted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'last_transition_at' => 'datetime',
        'rejected_at' => 'datetime',
        'context' => 'array',
        'accepted_snapshot_json' => 'array',
        'override_meta' => 'array',
    ];

    public function quoteVersion()
    {
        return $this->belongsTo(BettingQuoteVersion::class, 'betting_quote_version_id');
    }

    public function quoteSelection()
    {
        return $this->belongsTo(BettingQuoteSelection::class, 'betting_quote_selection_id');
    }

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }

    public function market()
    {
        return $this->belongsTo(BettingMarket::class, 'betting_market_id');
    }

    public function outcome()
    {
        return $this->belongsTo(BettingOutcome::class, 'betting_outcome_id');
    }

    public function user()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function rootUser()
    {
        return $this->belongsTo(CmsUser::class, 'root_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(CmsUser::class, 'reviewed_by_cms_user_id');
    }

    public function settlement()
    {
        return $this->hasOne(BettingSettlement::class, 'betting_acceptance_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(BettingActivityLog::class, 'betting_acceptance_id');
    }

    public function scopeExcludeCertification(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('context->is_certification')
                ->orWhere('context->is_certification', false);
        });
    }

    protected static function booted(): void
    {
        static::creating(function (self $acceptance): void {
            app(VirtualShowcaseMoneyFlowGuard::class)->assertUserIdsAllowed([
                $acceptance->cms_user_id,
                $acceptance->root_user_id,
            ], 'betting_acceptance_create');
        });
        static::created(function (self $acceptance): void {
            self::scheduleAcceptedCouponShare($acceptance);
        });
        static::updated(function (self $acceptance): void {
            if ($acceptance->wasChanged(['status', 'decision', 'accepted_at'])) {
                self::scheduleAcceptedCouponShare($acceptance);
            }
        });
    }

    private static function scheduleAcceptedCouponShare(self $acceptance): void
    {
        if ($acceptance->status !== 'accepted' || $acceptance->decision !== 'accept' || ! $acceptance->accepted_at) {
            return;
        }

        if (!class_exists(AcceptedCouponRoomDeliveryService::class)) return;
        try {
            if (! app(AcceptedCouponRoomDeliveryService::class)->capture($acceptance)) {
                return;
            }
            $publish = static function () use ($acceptance): void {
                try {
                    app(AcceptedCouponRoomDeliveryService::class)->process((int) $acceptance->id);
                } catch (\Throwable $exception) {
                    Log::warning('Accepted coupon room delivery attempt failed.', [
                        'error_type' => class_basename($exception),
                    ]);
                }
            };
            if (DB::transactionLevel() > 0) {
                DB::afterCommit($publish);
            } else {
                $publish();
            }
        } catch (\Throwable $exception) {
            Log::warning('Accepted coupon room publication scheduling failed.', [
                'error_type' => class_basename($exception),
            ]);
        }
    }
}
