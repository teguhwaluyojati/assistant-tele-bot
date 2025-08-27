<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUserCommand extends Model
{
    use HasFactory;
        protected $fillable = [
        'user_id',
        'command',
    ];
}
