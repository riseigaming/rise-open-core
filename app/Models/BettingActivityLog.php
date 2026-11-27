<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'betting_event_id',
        'betting_acceptance_id',
        'betting_settlement_id',
        'cms_user_id',
        'action',
        'level',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function acceptance()
    {
        return $this->belongsTo(BettingAcceptance::class, 'betting_acceptance_id');
    }

    public function settlement()
    {
        return $this->belongsTo(BettingSettlement::class, 'betting_settlement_id');
    }

    public function event()
    {
        return $this->belongsTo(BettingEvent::class, 'betting_event_id');
    }
}
