<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\URL;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telegram_user_id',
    ];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {

            return URL::signedRoute('avatar.show', ['filename' => $this->avatar]);
        } 
        
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relationship to TelegramUser
     */
    public function telegramUser()
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id', 'id');
    }

    /**
     * Check if user is admin (has telegram user with level = 1)
     */
    public function isAdmin()
    {
        return $this->telegramUser && $this->telegramUser->isAdmin();
    }
}
