<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TelegramUser;
use App\Models\LoginModel;

class DashboardController extends Controller
{
    protected $model_login;

    public function __construct()
    {
        $this->model_login = new LoginModel();
    }
    public function getUsers()
    {
        $users = TelegramUser::latest('last_interaction_at')->paginate(15);

        return response()->json($users);
    }

    public function historyLogin()
    {
       try{

        $data = $this->model_login->lastLogin();
        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
       }catch(\Exception $e){
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
       }
    }
}
