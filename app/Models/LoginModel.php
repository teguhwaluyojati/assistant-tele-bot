<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class LoginModel
{
    use HasFactory;

    protected $login_history = 'login_history';

    public function historyLogin()
    {
        $data = DB::table($this->login_history)
            ->select('*')
            ->where('email', auth()->user()->email)
            ->get();
        return $data;
    }

    public function lastLogin()
    {
        $data = DB::table($this->login_history)
            ->select('*')
            ->where('email', auth()->user()->email)
            ->orderBy('created_at', 'desc')
            ->first();
        return $data;
    }
}