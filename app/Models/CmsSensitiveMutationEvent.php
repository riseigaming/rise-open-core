<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsSensitiveMutationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'source',
        'route_name',
        'request_path',
        'command_name',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
