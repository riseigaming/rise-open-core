<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalGameSession extends Model
{
    protected $fillable = [
        'provider_alias',
        'provider_id',
        'cms_game_id',
        'provider_game_id',
        'cms_user_id',
        'root_user_id',
        'wallet_account_id',
        'wallet_account_type',
        'wallet_account_key',
        'wallet_currency',
        'mode',
        'session_token',
        'playability_snapshot',
        'launch_payload_digest',
        'launch_payload',
        'provider_launch_url',
        'provider_launch_reference',
        'provider_response',
        'status',
        'launched_at',
        'expires_at',
        'failed_at',
        'failure_reason',
        'last_callback_command',
        'last_callback_transaction_id',
        'last_callback_status',
        'last_callback_payload',
        'last_callback_at',
    ];

    protected $casts = [
        'playability_snapshot' => 'array',
        'launch_payload' => 'array',
        'provider_response' => 'array',
        'last_callback_payload' => 'array',
        'launched_at' => 'datetime',
        'expires_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_callback_at' => 'datetime',
    ];

    public function game()
    {
        return $this->belongsTo(CmsGame::class, 'cms_game_id');
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function user()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function walletAccount()
    {
        return $this->belongsTo(WalletAccount::class, 'wallet_account_id');
    }
}
