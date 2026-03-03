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
        'level',
    ];

    protected $appends = ['avatar_url'];

    protected $casts = [
        'last_interaction_at' => 'datetime',
    ];

    /**
     * Relationship to transactions
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id', 'user_id');
    }

    /**
     * Relationship to web User
     */
    public function webUser()
    {
        return $this->hasOne(User::class, 'telegram_user_id', 'id');
    }

    public function isSuperAdmin()
    {
        return (int) $this->level === 0;
    }

    public function isAdmin()
    {
        return in_array((int) $this->level, [0, 1], true);
    }

    public function getAvatarUrlAttribute()
    {
        return $this->webUser && $this->webUser->avatar_url ? $this->webUser->avatar_url : null;
    }
}
