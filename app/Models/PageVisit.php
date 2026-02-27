<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'ip_address',
        'user_agent',
        'user_agent_hash',
        'first_seen_at',
        'last_seen_at',
        'hit_count',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'hit_count' => 'integer',
    ];
}
