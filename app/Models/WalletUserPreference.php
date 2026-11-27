<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletUserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'cms_user_id',
        'active_wallet_account_id',
        'changed_at',
        'meta',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }

    public function activeWallet()
    {
        return $this->belongsTo(WalletAccount::class, 'active_wallet_account_id');
    }
}
