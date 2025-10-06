<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    use HasFactory;
    
    protected $table = 'login_history';

    protected $fillable = [
        'email',
        'ip_address',
    ];
}
