<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    protected $fillable = [
        'email',
        'telegram_username',
        'code',
        'name',
        'password',
        'verified',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
    ];

    public function isExpired()
    {
        return now()->isAfter($this->expires_at);
    }
}
