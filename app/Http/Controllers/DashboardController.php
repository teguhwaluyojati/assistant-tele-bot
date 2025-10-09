<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;

class DashboardController extends Controller
{
    public function getUsers()
    {
        $users = TelegramUser::latest('last_interaction_at')->paginate(15);

        return response()->json($users);
    }
}
