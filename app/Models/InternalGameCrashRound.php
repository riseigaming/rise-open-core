<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalGameCrashRound extends Model
{
    use HasFactory;
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'provider_id',
        'cms_game_id',
        'round_public_id',
        'game_code',
        'status',
        'currency',
        'is_demo',
        'crash_multiplier',
        'server_seed',
        'server_seed_hash',
        'fair_hash',
        'betting_opens_at',
        'betting_closes_at',
        'starts_at',
        'crashed_at',
        'settled_at',
        'raw_result',
    ];

    protected $casts = [
        'raw_result' => 'array',
        'is_demo' => 'boolean',
        'betting_opens_at' => 'datetime',
        'betting_closes_at' => 'datetime',
        'starts_at' => 'datetime',
        'crashed_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function game()
    {
        return $this->belongsTo(CmsGame::class, 'cms_game_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function playerRounds()
    {
        return $this->hasMany(InternalGameRound::class, 'internal_game_crash_round_id');
    }
}
