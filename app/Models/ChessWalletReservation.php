<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChessWalletReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_reservation_id',
        'cms_placement_ref',
        'provider_id',
        'cms_user_id',
        'reserve_transaction_id',
        'release_transaction_id',
        'settlement_transaction_id',
        'partner_code',
        'external_user_id',
        'idempotency_key',
        'request_id',
        'placement_intent_id',
        'placed_bet_id',
        'market_instance_id',
        'quote_ref',
        'quote_expires_at',
        'stake_amount',
        'payout_amount',
        'currency',
        'status',
        'reject_reason',
        'release_reason',
        'result_status',
        'request_payload',
        'reserve_response_payload',
        'settlement_summary',
        'last_settlement_payload',
        'reserved_at',
        'released_at',
        'settled_at',
        'last_settlement_handoff_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'reserve_response_payload' => 'array',
        'settlement_summary' => 'array',
        'last_settlement_payload' => 'array',
        'quote_expires_at' => 'datetime',
        'reserved_at' => 'datetime',
        'released_at' => 'datetime',
        'settled_at' => 'datetime',
        'last_settlement_handoff_at' => 'datetime',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function user()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function reserveTransaction()
    {
        return $this->belongsTo(CmsUserTransaction::class, 'reserve_transaction_id');
    }

    public function releaseTransaction()
    {
        return $this->belongsTo(CmsUserTransaction::class, 'release_transaction_id');
    }

    public function settlementTransaction()
    {
        return $this->belongsTo(CmsUserTransaction::class, 'settlement_transaction_id');
    }
}
