<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    use HasFactory;
        protected $fillable = [
        'user_id',
        'username',
        'first_name',
        'last_name',
        'last_interaction_at',
    ];

    protected $casts = [
        'last_interaction_at' => 'datetime',
    ];
}
