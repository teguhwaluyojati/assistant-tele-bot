<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LoginModel extends Model
{
    use HasFactory;

    protected $table = 'login_history';
    protected $fillable = ['email', 'ip_address', 'created_at'];
    public $timestamps = false;

    /**
     * Get all login history for authenticated user (with cache)
     */
    public function historyLogin($cacheDuration = 60)
    {
        $userEmail = auth()->user()->email;
        $cacheKey = "login_history_{$userEmail}";

        return Cache::remember($cacheKey, $cacheDuration, function () use ($userEmail) {
            return static::byEmail($userEmail)->get();
        });
    }

    /**
     * Get last login for authenticated user (with cache)
     */
    public function lastLogin($cacheDuration = 60)
    {
        $userEmail = auth()->user()->email;
        $cacheKey = "last_login_{$userEmail}";

        return Cache::remember($cacheKey, $cacheDuration, function () use ($userEmail) {
            return static::byEmail($userEmail)->latest('created_at')->first();
        });
    }

    /**
     * Query scope: Filter by email
     */
    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Query scope: Recently ordered
     */
    public function scopeRecent($query)
    {
        return $query->latest('created_at');
    }

    /**
     * Get recent logins with caching (admin: all, user: own)
     */
    public static function getRecentLogins($limit = 10, $isAdmin = false, $userEmail = null, $cacheDuration = 60)
    {
        if ($isAdmin) {
            $cacheKey = "recent_logins_all";
            return Cache::remember($cacheKey, $cacheDuration, function () use ($limit) {
                return static::recent()
                    ->select('email', 'ip_address', 'created_at')
                    ->limit($limit)
                    ->get();
            });
        } else {
            $cacheKey = "recent_login_{$userEmail}";
            return Cache::remember($cacheKey, $cacheDuration, function () use ($userEmail) {
                return static::byEmail($userEmail)
                    ->select('email', 'ip_address', 'created_at')
                    ->latest('created_at')
                    ->limit(1)
                    ->get();
            });
        }
    }
}