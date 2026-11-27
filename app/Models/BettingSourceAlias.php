<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BettingSourceAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'adapter_key',
        'alias_type',
        'source_context',
        'source_value',
        'canonical_value',
        'notes',
        'is_active',
        'cms_user_id',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(CmsUser::class, 'cms_user_id');
    }
}
